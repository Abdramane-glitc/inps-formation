<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgentRequest;
use App\Models\Agent;
use App\Models\Direction;
use App\Models\Nomenclature;
use App\Models\Service;
use App\Models\SessionFormation;
use App\Services\Audit;
use App\Services\ExportService;
use App\Support\Profils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Module B — Référentiel du personnel (désactivation logique, jamais de suppression). */
class AgentController extends Controller
{
    /**
     * Choix d'agents dans les formulaires (29/10/2026) : recherche côté serveur dans TOUT le personnel visible
     * (même cloisonnement que la page Personnel), pour les listes trop longues pour être affichées en entier.
     * Chaque mot tapé doit se retrouver dans le matricule, le nom ou le prénom, dans n'importe quel ordre.
     * Avec « session », les participants actuels de cette formation sont exclus.
     */
    public function choisir(Request $r): JsonResponse
    {
        $d = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'session' => ['nullable', 'integer']]);
        $mots = array_slice(preg_split('/\s+/u', trim($d['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), 0, 5);
        if (mb_strlen(implode('', $mots)) < 2) {
            return response()->json(['agents' => [], 'plus' => false]);
        }
        $q = Agent::visiblesPar($r->user())->where('actif', true)->with(['service', 'direction']);
        foreach ($mots as $m) {
            $t = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $m).'%';
            $q->where(fn ($w) => $w->where('matricule', 'like', $t)->orWhere('nom', 'like', $t)->orWhere('prenom', 'like', $t));
        }
        if (! empty($d['session']) && ($s = SessionFormation::find($d['session']))) {
            $q->whereNotIn('id', $s->participantsActifs()->pluck('agent_id'));
        }
        $agents = $q->orderBy('nom')->orderBy('prenom')->limit(31)->get();

        return response()->json([
            'agents' => $agents->take(30)->map(fn (Agent $a) => ['id' => $a->id, 'matricule' => $a->matricule, 'nom' => $a->nom_complet,
                'structure' => $a->service?->nom ?? ($a->direction?->sigle ?: $a->direction?->nom) ?? ''])->values(),
            'plus' => $agents->count() > 30,
        ]);
    }

    public function index(Request $r): View
    {
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'direction_id' => ['nullable', 'integer'], 'service_id' => ['nullable', 'integer'],
            'actif' => ['nullable', 'in:0,1,tous'], 'tri' => ['nullable', 'in:matricule,nom,service']]);
        if (! $r->user()->aPermission('agents.voir_tous')) {
            unset($f['direction_id'], $f['service_id']); // un responsable de structure ne filtre pas sur d'autres services
        }
        $q = Agent::visiblesPar($r->user())->with(['direction', 'service'])->recherche($f['q'] ?? null)
            ->when($f['direction_id'] ?? null, fn ($q, $v) => $q->where('direction_id', $v))
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
            ->when(($f['actif'] ?? '1') !== 'tous', fn ($q) => $q->where('actif', ($f['actif'] ?? '1') === '1'));
        match ($f['tri'] ?? 'nom') {
            'matricule' => $q->orderBy('matricule'),
            'service' => $q->orderBy('service_id')->orderBy('nom'),
            default => $q->orderBy('nom')->orderBy('prenom'),
        };

        // Seule la RH (tous les agents) voit les filtres direction / service ; un responsable de structure ne voit que son service
        $tous = $r->user()->aPermission('agents.voir_tous');

        return view('agents.index', ['agents' => $q->paginate(25)->withQueryString(), 'f' => $f, 'tous' => $tous,
            'monService' => $tous ? null : $r->user()->service,
            'directions' => $tous ? Direction::actives()->orderBy('nom')->get() : collect(),
            'services' => $tous ? Service::actifs()->with('direction')->orderBy('nom')->get() : collect()]);
    }

    public function create(): View
    {
        return view('agents.form', ['agent' => new Agent] + $this->listes());
    }

    public function store(AgentRequest $r): RedirectResponse
    {
        $a = new Agent($r->validated());
        $a->save();
        Audit::enregistrer('agent.creation', $a, "Création de l'agent {$a->matricule}");

        return redirect()->route('agents.show', $a)->with('succes', 'Agent enregistré.');
    }

    public function show(Request $r, Agent $agent): View
    {
        $this->authorize('view', $agent);
        $inscriptions = $agent->inscriptions()->with(['session.action.domaine', 'attestation'])
            ->join('sessions_formation', 'sessions_formation.id', '=', 'inscriptions.session_id')
            ->join('actions_formation', fn ($j) => $j->on('actions_formation.id', '=', 'sessions_formation.action_id')->whereNull('actions_formation.deleted_at'))
            ->orderByDesc('sessions_formation.date_debut')->select('inscriptions.*')->get();

        return view('agents.show', ['agent' => $agent->load(['direction', 'service', 'statutProfessionnel', 'user']), 'inscriptions' => $inscriptions]);
    }

    public function historiquePdf(Agent $agent, ExportService $export): Response
    {
        $this->authorize('view', $agent);
        $inscriptions = $agent->inscriptions()->with(['session.action.domaine'])->get()->sortByDesc(fn ($i) => $i->session->date_debut);
        Audit::enregistrer('export.historique_agent', $agent, "Export de l'historique de formation de {$agent->matricule}");

        return $export->pdf('pdf.historique-agent', compact('agent', 'inscriptions'), 'historique-'.$agent->matricule);
    }

    public function edit(Agent $agent): View
    {
        return view('agents.form', ['agent' => $agent] + $this->listes());
    }

    public function update(AgentRequest $r, Agent $agent): RedirectResponse
    {
        $agent->fill($r->validated());
        $champs = array_keys($agent->getDirty());
        $agent->save();
        Audit::enregistrer('agent.modification', $agent, "Modification de l'agent {$agent->matricule}", ['champs' => $champs]);

        return redirect()->route('agents.show', $agent)->with('succes', 'Fiche mise à jour.');
    }

    public function activation(Request $r, Agent $agent): RedirectResponse
    {
        $d = $r->validate(['actif' => ['required', 'boolean'], 'motif' => ['required_if:actif,0', 'nullable', 'string', 'max:255']]);
        $agent->actif = (bool) $d['actif'];
        $agent->date_desactivation = $agent->actif ? null : now();
        $agent->motif_desactivation = $agent->actif ? null : $d['motif'];
        $agent->save();
        // 2.3.0 : un agent désactivé (départ, mutation…) perd aussi son accès d'agent à l'application
        $compte = $agent->user()->first();
        if (! $agent->actif && $compte && Profils::estAgent($compte) && $compte->actif) {
            $compte->forceFill(['actif' => false])->save();
            DB::table('sessions')->where('user_id', $compte->id)->delete();
        }
        Audit::enregistrer($agent->actif ? 'agent.reactivation' : 'agent.desactivation', $agent, ($agent->actif ? 'Réactivation' : 'Désactivation')." de l'agent {$agent->matricule}");

        return back()->with('succes', $agent->actif ? 'Agent réactivé.' : 'Agent désactivé (ses données et son historique sont conservés).');
    }

    public function export(Request $r, ExportService $export): Response
    {
        $agents = Agent::visiblesPar($r->user())->with(['direction', 'service', 'statutProfessionnel'])->orderBy('nom')->get();
        Audit::enregistrer('export.agents', 'agent', 'Export du référentiel du personnel', ['lignes' => $agents->count()]);

        return $export->excel('Référentiel du personnel', ['Matricule', 'Nom', 'Prénom', 'Direction', 'Service', 'Fonction', 'Statut', 'E-mail pro.', 'Téléphone pro.', 'Actif'],
            $agents->map(fn ($a) => [$a->matricule, $a->nom, $a->prenom, $a->direction?->nom, $a->service?->nom, $a->fonction, $a->statutProfessionnel?->libelle, $a->email_pro, $a->telephone_pro, $a->actif ? 'Oui' : 'Non'])->all(),
            'referentiel-personnel-'.now()->format('Ymd'));
    }

    private function listes(): array
    {
        return ['directions' => Direction::actives()->get(), 'services' => Service::actifs()->with('direction')->get(), 'statuts' => Nomenclature::deType('statut_professionnel')->get()];
    }
}
