<?php

namespace App\Http\Controllers;

use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\BesoinFormation;
use App\Models\FormationDiplomante;
use App\Models\Organisme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Recherche générale de la barre du haut (2.6.0) : agents, formations, demandes, organismes et formations diplômantes.
 * Chaque rubrique n'est interrogée que si le compte a le droit de la voir, avec le même cloisonnement par service
 * que les listes (scopes « visiblesPar » et règle d'accès des demandes). Au plus LIMITE résultats par rubrique.
 * Réponse JSON (28/10/2026) pour les suggestions affichées sous la barre pendant la frappe (resources/js/app.js).
 */
class RechercheController extends Controller
{
    private const LIMITE = 8;

    public function index(Request $r): View|JsonResponse
    {
        $q = trim((string) ($r->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));
        $u = $r->user();
        $resultats = [];
        if (mb_strlen($q) >= 2) {
            $t = '%'.addcslashes($q, '%_\\').'%';
            if ($u->aUneDesPermissions('agents.voir_tous', 'agents.voir_service')) {
                $resultats['Personnel'] = Agent::visiblesPar($u)->recherche($q)->with('service')->orderBy('nom')->limit(self::LIMITE)->get()
                    ->map(fn ($a) => [$a->nom_complet, $a->matricule.($a->service ? ' — '.$a->service->nom : ''), route('agents.show', $a)]);
            }
            if ($u->aPermission('actions.voir')) {
                $resultats['Formations'] = ActionFormation::visiblesPar($u)->where(fn ($w) => $w->where('intitule', 'like', $t)->orWhere('reference', 'like', $t))
                    ->orderByDesc('annee')->limit(self::LIMITE)->get()
                    ->map(fn ($a) => [$a->intitule, $a->reference.' — '.$a->annee, route('formations.show', $a)]);
            }
            if ($u->aUneDesPermissions('besoins.voir_tous', 'besoins.voir_service')) {
                $resultats['Demandes'] = BesoinFormation::visiblesPar($u)->where(fn ($w) => $w->where('intitule', 'like', $t)->orWhere('reference', 'like', $t))
                    ->with('service')->latest()->limit(self::LIMITE)->get()->filter(fn ($b) => $u->can('view', $b))
                    ->map(fn ($b) => [$b->intitule, $b->reference.($b->service ? ' — '.$b->service->nom : ''), route('besoins.show', $b)]);
            }
            if ($u->aPermission('organismes.voir')) {
                $resultats['Organismes'] = Organisme::where(fn ($w) => $w->where('raison_sociale', 'like', $t)->orWhere('sigle', 'like', $t))
                    ->orderBy('raison_sociale')->limit(self::LIMITE)->get()
                    ->map(fn ($o) => [$o->raison_sociale, $o->sigle, route('organismes.show', $o)]);
            }
            if ($u->aPermission('diplomantes.gerer')) {
                $resultats['Formations diplômantes'] = FormationDiplomante::with('agent')
                    ->where(fn ($w) => $w->where('intitule', 'like', $t)->orWhere('etablissement', 'like', $t)->orWhere('reference', 'like', $t)
                        ->orWhereHas('agent', fn ($a) => $a->where('nom', 'like', $t)->orWhere('matricule', 'like', $t)))
                    ->latest()->limit(self::LIMITE)->get()
                    ->map(fn ($d) => [$d->libelle('niveau').' — '.$d->intitule, $d->agent->nom_complet.' — '.$d->etablissement, route('diplomantes.show', $d)]);
            }
            $resultats = array_filter($resultats, fn ($l) => $l->isNotEmpty());
        }

        if ($r->expectsJson()) {
            // Suggestions : mêmes résultats que la page, au format [rubrique => [{titre, detail, url}]]
            return response()->json(['q' => $q, 'page' => route('recherche', ['q' => $q]), 'rubriques' => collect($resultats)
                ->map(fn ($lignes, $rubrique) => ['titre' => $rubrique, 'lignes' => $lignes->map(fn ($l) => ['titre' => $l[0], 'detail' => $l[1], 'url' => $l[2]])->values()])
                ->values()]);
        }

        return view('recherche.index', ['q' => $q, 'resultats' => $resultats]);
    }
}
