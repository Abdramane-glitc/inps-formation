<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeReference;
use App\Models\Direction;
use App\Models\Domaine;
use App\Models\Nomenclature;
use App\Models\Service;
use App\Services\Audit;
use App\Support\Utilisations;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Référentiels paramétrables : directions, services, domaines, nomenclatures, années de référence.
 * Un élément utilisé ne se supprime pas : on le désactive (les données historiques restent cohérentes).
 * Depuis la version 2.1.0, un élément utilisé nulle part (saisi par erreur, doublon) peut être supprimé.
 */
class ReferentielController extends Controller
{
    private function definition(string $type): array
    {
        return match (true) {
            $type === 'directions' => ['Directions', Direction::class],
            $type === 'services' => ['Services', Service::class],
            $type === 'domaines' => ['Domaines de formation', Domaine::class],
            $type === 'annees' => ['Années de référence', AnneeReference::class],
            array_key_exists($type, Nomenclature::TYPES) => [Nomenclature::TYPES[$type], Nomenclature::class],
            default => abort(404),
        };
    }

    public function index(string $type): View
    {
        [$titre, $classe] = $this->definition($type);
        $q = $classe::query();
        if ($classe === Nomenclature::class) {
            $q->where('type', $type)->orderBy('ordre');
        }
        if ($classe === Service::class) {
            $q->with('direction');
        }
        $elements = $classe === AnneeReference::class ? $q->orderByDesc('annee')->get() : $q->orderBy($classe === Nomenclature::class ? 'ordre' : 'nom')->get();

        return view('admin.referentiels.index', ['type' => $type, 'titre' => $titre, 'elements' => $elements,
            'directions' => Direction::orderBy('nom')->get(), 'types' => Nomenclature::TYPES, 'usages' => $this->usages($classe, $elements)]);
    }

    /** @return array<int, array<string,int>> id => [libellé => nombre] */
    private function usages(string $classe, $elements): array
    {
        $m = new $classe;
        if ($m instanceof AnneeReference) {
            // Les années ne sont pas des clés étrangères : on cherche leur valeur dans les tables qui portent une année
            $tables = collect(['besoins_formation', 'actions_formation', 'plans_annuels', 'plans_formation'])->filter(fn ($t) => Schema::hasTable($t))
                ->mapWithKeys(fn ($t) => [$t => 'annee'])->all();

            return Utilisations::compter($m->getTable(), $elements->pluck('id')->all(), $tables, $elements->pluck('annee', 'id')->all());
        }

        return Utilisations::compter($m->getTable(), $elements->pluck('id')->all());
    }

    /** Supprime un élément utilisé nulle part (2.1.0). Sinon, la raison est donnée et la désactivation proposée. */
    public function destroy(string $type, int $id): RedirectResponse
    {
        [, $classe] = $this->definition($type);
        $m = $classe::findOrFail($id);
        if ($m instanceof Nomenclature && $m->type !== $type) {
            abort(404);
        }
        $usages = $this->usages($classe, collect([$m]))[$m->id] ?? [];
        $nom = $m->nom ?? $m->libelle ?? (string) $m->annee;
        if ($usages) {
            return back()->with('erreur', "« $nom » ne peut pas être supprimé : il est utilisé par ".Utilisations::texte($usages).'. Décochez « Actif » pour le désactiver.');
        }
        try {
            DB::transaction(fn () => $m->delete());
        } catch (QueryException) {
            return back()->with('erreur', "« $nom » est utilisé ailleurs dans l'application et ne peut pas être supprimé : désactivez-le.");
        }
        Audit::enregistrer('referentiel.suppression', $m->getMorphClass(), "Référentiel « $type » : « $nom » supprimé (jamais utilisé)", ['id' => $id, 'code' => $m->code ?? null]);

        return back()->with('succes', "« $nom » supprimé.");
    }

    public function store(Request $r, string $type): RedirectResponse
    {
        [, $classe] = $this->definition($type);
        $m = new $classe;
        $this->remplir($r, $m, $type);

        return back()->with('succes', 'Élément ajouté.');
    }

    public function update(Request $r, string $type, int $id): RedirectResponse
    {
        [, $classe] = $this->definition($type);
        $m = $classe::findOrFail($id);
        if ($m instanceof Nomenclature && $m->type !== $type) {
            abort(404);
        }
        $this->remplir($r, $m, $type);

        return back()->with('succes', 'Élément mis à jour.');
    }

    private function remplir(Request $r, $m, string $type): void
    {
        $r->merge(['actif' => $r->boolean('actif')]);
        $id = $m->exists ? $m->id : null;
        if ($m instanceof AnneeReference) {
            // 2.5.0 : l'année et une case « Demandes ouvertes » (la date limite et la date de clôture ne sont plus saisies)
            $d = $r->validate(['annee' => ['required', 'integer', 'between:2000,2100', Rule::unique('annees_reference', 'annee')->ignore($id)]]);
            $d['statut'] = $r->boolean('ouverte') ? 'ouverte' : 'cloturee';
        } elseif ($m instanceof Nomenclature) {
            $d = $r->validate(['code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('nomenclatures', 'code')->where('type', $type)->ignore($id)],
                'libelle' => ['required', 'string', 'max:190'], 'ordre' => ['nullable', 'integer', 'min:0', 'max:999'], 'actif' => ['boolean']]);
            $d['type'] = $type;
            $d['ordre'] ??= 0;
        } else {
            $table = $m->getTable();
            $regles = ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique($table, 'code')->ignore($id)], 'nom' => ['required', 'string', 'max:190'], 'actif' => ['boolean']];
            if ($m instanceof Service) {
                $regles['direction_id'] = ['required', 'integer', 'exists:directions,id'];
            }
            if ($m instanceof Direction) {
                $regles['sigle'] = ['nullable', 'string', 'max:30'];
            }
            if ($m instanceof Domaine) {
                $regles['description'] = ['nullable', 'string', 'max:2000'];
            }
            $d = $r->validate($regles);
        }
        $m->fill($d)->save();
        Audit::enregistrer('referentiel.'.($id ? 'modification' : 'creation'), $m, "Référentiel « $type » : ".($d['nom'] ?? $d['libelle'] ?? $d['annee']));
    }
}
