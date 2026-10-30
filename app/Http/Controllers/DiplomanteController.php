<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Direction;
use App\Models\FormationDiplomante as D;
use App\Models\Nomenclature;
use App\Services\Audit;
use App\Services\DocumentService;
use App\Services\ExportService;
use App\Services\GenerateurReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Formations diplômantes des agents (2.5.0) : saisie et suivi par la RH (permission diplomantes.gerer). */
class DiplomanteController extends Controller
{
    public function index(Request $r): View
    {
        [$f, $q] = $this->requete($r);

        return view('diplomantes.index', ['diplomantes' => $q->paginate(25)->withQueryString(), 'f' => $f,
            'directions' => Direction::orderBy('nom')->get(['id', 'nom']),
            'annees' => D::query()->distinct()->orderByDesc('annee_debut')->pluck('annee_debut'),
            'compte' => D::query()->selectRaw('situation, count(*) n')->groupBy('situation')->pluck('n', 'situation')]);
    }

    public function export(Request $r, ExportService $export): Response
    {
        $lignes = $this->requete($r)[1]->get();
        Audit::enregistrer('export.diplomantes', 'diplomante', 'Export des formations diplômantes', ['lignes' => $lignes->count()]);

        return $export->excel('Formations diplômantes des agents', ['Référence', 'Matricule', 'Agent', 'Direction', 'Service', 'Niveau', 'Intitulé / spécialité',
            'Établissement', 'Ville', 'Pays', 'Année académique', 'Durée (ans)', 'Mode', 'Prise en charge', 'Situation', 'Date d\'obtention'],
            $lignes->map(fn (D $d) => [$d->reference, $d->agent->matricule, $d->agent->nom_complet, $d->agent->direction?->nom, $d->agent->service?->nom,
                $d->libelle('niveau'), $d->intitule, $d->etablissement, $d->ville, $d->pays, $d->annee_academique, $d->duree_annees,
                $d->libelle('mode'), $d->libelle('prise_en_charge'), $d->libelle('situation'), $d->date_obtention?->format('d/m/Y')])->all(),
            'formations-diplomantes-'.now()->format('Ymd'));
    }

    public function create(Request $r): View
    {
        $d = new D(['annee_debut' => (int) now()->format('Y') - (now()->month < 9 ? 1 : 0), 'situation' => 'en_cours', 'mode' => 'presentiel']);
        $d->agent_id = $r->integer('agent') ?: null;

        return view('diplomantes.form', ['d' => $d, 'agents' => $this->agents()]);
    }

    public function store(Request $r): RedirectResponse
    {
        $donnees = $this->donnees($r);
        $d = DB::transaction(function () use ($donnees, $r) {
            $d = new D($donnees);
            $d->reference = GenerateurReference::suivante('formations_diplomantes', 'reference', 'DIP', (int) $donnees['annee_debut']);
            $d->cree_par = $r->user()->id;
            $d->save();

            return $d;
        });
        Audit::enregistrer('diplomante.creation', $d, "Formation diplômante {$d->reference} : {$d->agent->nom_complet}, {$d->libelle('niveau')} {$d->intitule}");

        return redirect()->route('diplomantes.show', $d)->with('succes', 'Formation diplômante enregistrée. Vous pouvez y joindre les pièces (inscription, diplôme…).');
    }

    public function show(D $diplomante): View
    {
        return view('diplomantes.show', ['d' => $diplomante->load(['agent.direction', 'agent.service', 'createur', 'documents.categorie']),
            'categoriesDoc' => Nomenclature::deType('categorie_document')->get()]);
    }

    public function edit(D $diplomante): View
    {
        return view('diplomantes.form', ['d' => $diplomante, 'agents' => $this->agents($diplomante->agent_id)]);
    }

    public function update(Request $r, D $diplomante): RedirectResponse
    {
        $avant = $diplomante->situation;
        $diplomante->fill($this->donnees($r));
        $diplomante->modifie_par = $r->user()->id;
        $diplomante->save();
        Audit::enregistrer('diplomante.modification', $diplomante, "Formation diplômante {$diplomante->reference} modifiée"
            .($avant !== $diplomante->situation ? ' : '.D::SITUATIONS[$avant].' → '.$diplomante->libelle('situation') : ''));

        return redirect()->route('diplomantes.show', $diplomante)->with('succes', 'Formation diplômante mise à jour.');
    }

    public function destroy(D $diplomante, DocumentService $documents): RedirectResponse
    {
        DB::transaction(function () use ($diplomante, $documents) {
            foreach ($diplomante->documents as $doc) {
                $documents->retirer($doc);
            }
            $diplomante->delete();
        });
        Audit::enregistrer('diplomante.suppression', 'diplomante', "Suppression de la formation diplômante {$diplomante->reference} ({$diplomante->agent->matricule}, {$diplomante->libelle('niveau')} {$diplomante->intitule})");

        return redirect()->route('diplomantes.index')->with('succes', 'Formation diplômante supprimée.');
    }

    /** @return array{0:array, 1:Builder} */
    private function requete(Request $r): array
    {
        $f = array_filter($r->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'niveau' => ['nullable', Rule::in(array_keys(D::NIVEAUX))],
            'situation' => ['nullable', Rule::in(array_keys(D::SITUATIONS))],
            'prise_en_charge' => ['nullable', Rule::in(array_keys(D::PRISES_EN_CHARGE))],
            'annee' => ['nullable', 'integer', 'between:1980,2100'],
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
        ]), fn ($v) => $v !== null && $v !== '');
        $like = fn ($v) => '%'.addcslashes($v, '%_\\').'%';
        $q = D::query()->with(['agent.direction', 'agent.service'])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('intitule', 'like', $like($v))->orWhere('etablissement', 'like', $like($v))
                ->orWhere('reference', 'like', $like($v))
                ->orWhereHas('agent', fn ($a) => $a->where('matricule', 'like', $like($v))->orWhere('nom', 'like', $like($v))->orWhere('prenom', 'like', $like($v)))))
            ->when($f['niveau'] ?? null, fn ($q, $v) => $q->where('niveau', $v))
            ->when($f['situation'] ?? null, fn ($q, $v) => $q->where('situation', $v))
            ->when($f['prise_en_charge'] ?? null, fn ($q, $v) => $q->where('prise_en_charge', $v))
            ->when($f['annee'] ?? null, fn ($q, $v) => $q->where('annee_debut', $v))
            ->when($f['direction_id'] ?? null, fn ($q, $v) => $q->whereHas('agent', fn ($a) => $a->where('direction_id', $v)))
            ->orderByDesc('annee_debut')->orderByDesc('id');

        return [$f, $q];
    }

    private function donnees(Request $r): array
    {
        $d = $r->validate([
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'niveau' => ['required', Rule::in(array_keys(D::NIVEAUX))],
            'intitule' => ['required', 'string', 'max:255'],
            'etablissement' => ['required', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:120'],
            'pays' => ['nullable', 'string', 'max:120'],
            'annee_debut' => ['required', 'integer', 'between:1980,2100'],
            'duree_annees' => ['nullable', 'integer', 'between:1,10'],
            'mode' => ['required', Rule::in(array_keys(D::MODES))],
            'prise_en_charge' => ['required', Rule::in(array_keys(D::PRISES_EN_CHARGE))],
            'situation' => ['required', Rule::in(array_keys(D::SITUATIONS))],
            'date_obtention' => ['nullable', 'date', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:3000'],
        ], [
            'agent_id.required' => 'Choisissez l\'agent concerné.',
            'date_obtention.before_or_equal' => 'La date d\'obtention ne peut pas être dans le futur.',
        ], ['annee_debut' => 'année académique', 'intitule' => 'intitulé', 'etablissement' => 'établissement', 'prise_en_charge' => 'prise en charge']);
        if ($d['situation'] !== 'obtenu') {
            $d['date_obtention'] = null; // la date n'a de sens que si le diplôme est obtenu
        }

        return $d;
    }

    private function agents(?int $inclure = null)
    {
        return Agent::where(fn ($q) => $q->where('actif', true)->when($inclure, fn ($w) => $w->orWhere('id', $inclure)))
            ->orderBy('nom')->orderBy('prenom')->get(['id', 'matricule', 'nom', 'prenom']);
    }
}
