<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Service;
use App\Services\AccesAgentService;
use App\Services\Audit;
use App\Services\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Personnel › Accès des agents (2.4.0) : codes d'activation à 6 chiffres, affichés à l'écran.
 * Générer ne change pas un code encore valable (un double clic est sans effet) ; « Nouveau code » le remplace.
 */
class AccesAgentController extends Controller
{
    /** Agents affichés par page (et cochés au plus par « Tout cocher »). */
    public const LOT = 50;

    public function __construct(private AccesAgentService $acces) {}

    public function index(Request $r): View
    {
        $f = $this->filtresAcces($r);

        return view('agents.acces', ['agents' => $this->requete($r, $f)->paginate(self::LOT)->withQueryString(), 'f' => $f,
            'services' => Service::optionsParDirection(), 'jours' => AccesAgentService::JOURS_VALIDITE,
            'sansAcces' => Agent::visiblesPar($r->user())->where('actif', true)->whereDoesntHave('user')->count()]);
    }

    /** Codes pour les agents cochés : compte créé si besoin ; un code encore valable est conservé. */
    public function generer(Request $r): RedirectResponse
    {
        $d = $r->validate(['agents' => ['required', 'array', 'min:1', 'max:500'], 'agents.*' => ['integer']], ['agents.required' => 'Cochez au moins un agent.']);
        $ids = Agent::visiblesPar($r->user())->whereIn('id', $d['agents'])->pluck('id')->all();
        $res = $this->acces->genererCodes($ids, $r->user());

        return back()->with('succes', $res['codes'] || $res['comptes']
            ? "{$res['codes']} code(s) d'activation attribué(s)".($res['comptes'] ? ", dont {$res['comptes']} nouvel(s) accès" : '').' : ils s\'affichent dans la colonne « Code ».'
            : 'Rien à faire : les agents cochés ont déjà un code valable (affiché dans la colonne « Code »).');
    }

    /** Remplace le code d'un agent (mot de passe oublié, code perdu ou expiré). */
    public function nouveauCode(Request $r, Agent $agent): RedirectResponse
    {
        abort_unless(Agent::visiblesPar($r->user())->whereKey($agent->id)->exists(), 403);
        $this->acces->genererCodes([$agent->id], $r->user(), nouveau: true);
        $u = $agent->user()->first();

        return back()->with('succes', "Nouveau code pour {$agent->nom_complet} : {$u?->code_activation_affiche} (valable ".AccesAgentService::JOURS_VALIDITE.' jours).');
    }

    /** Liste imprimable des codes valables (filtres de la page) : une ligne par agent. */
    public function liste(Request $r, ExportService $export): Response
    {
        $f = $this->filtresAcces($r);
        $agents = $this->requete($r, $f)->whereHas('user', fn ($q) => $q->whereNotNull('code_activation')->where('code_activation_expire_le', '>', now()))
            ->get()->filter(fn ($a) => $a->user->aCodeValide())->sortBy(fn ($a) => ($a->service?->nom ?? '~').' '.$a->nom)->values();
        Audit::enregistrer('export.codes_activation', 'user', 'Liste des codes d\'activation imprimée', ['agents' => $agents->count()]);

        return $export->pdf('pdf.codes-activation', ['agents' => $agents], 'codes-activation-'.now()->format('Ymd-His'));
    }

    private function filtresAcces(Request $r): array
    {
        return $r->validate(['q' => ['nullable', 'string', 'max:100'], 'service_id' => ['nullable', 'integer'], 'etat' => ['nullable', Rule::in(['sans', 'code', 'actives'])]]);
    }

    private function requete(Request $r, array $f)
    {
        return Agent::visiblesPar($r->user())->where('actif', true)->with(['service', 'user'])
            ->recherche($f['q'] ?? null)
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
            ->when(($f['etat'] ?? null) === 'sans', fn ($q) => $q->whereDoesntHave('user'))
            ->when(($f['etat'] ?? null) === 'code', fn ($q) => $q->whereHas('user', fn ($u) => $u->whereNotNull('code_activation')))
            ->when(($f['etat'] ?? null) === 'actives', fn ($q) => $q->whereHas('user', fn ($u) => $u->whereNull('code_activation')->whereNotNull('derniere_connexion_le')))
            ->orderBy('nom')->orderBy('prenom');
    }
}
