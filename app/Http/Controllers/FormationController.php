<?php

namespace App\Http\Controllers;

use App\Enums\StatutSession;
use App\Http\Requests\FormationRequest;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\Attestation;
use App\Models\Domaine;
use App\Models\Formateur;
use App\Models\Inscription;
use App\Models\Nomenclature;
use App\Models\Organisme;
use App\Models\Service;
use App\Models\SessionFormation;
use App\Services\ActionService;
use App\Services\ConvocationService;
use App\Services\EvaluationService;
use App\Services\InscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Formations — parcours en trois étapes (1.5.0) : la formation, ses participants, les attestations.
 * Un seul écran réunit ce qui est enseigné (action), la période (session unique),
 * les participants (avec « a suivi la formation »), les attestations et les documents.
 */
class FormationController extends Controller
{
    public function __construct(private ActionService $actions) {}

    public function index(Request $r): View
    {
        $f = $r->validate(['annee' => ['nullable', 'integer'], 'statut' => ['nullable', Rule::in(array_merge(array_column(StatutSession::cases(), 'value'), ['a_terminer']))], 'type' => ['nullable', 'in:interne,externe'],
            'domaine_id' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100'], 'historique' => ['nullable', 'in:0,1']]);
        $sid = ActionFormation::serviceLimite($r->user());
        $formations = ActionFormation::visiblesPar($r->user())->with(['domaine', 'organisme', 'besoin.service', 'session' => fn ($q) => $q->withCount(['inscriptions as occupees' => fn ($i) => $i->whereIn('statut', SessionFormation::STATUTS_OCCUPANT_PLACE)
            ->when($sid, fn ($w) => $w->whereHas('agent', fn ($a) => $a->where('service_id', $sid)))])])
            ->when($f['annee'] ?? null, fn ($q, $v) => $q->where('annee', $v))
            ->when($f['statut'] ?? null, fn ($q, $v) => $q->whereHas('sessions', fn ($s) => $v === 'a_terminer'
                ? $s->where('statut', StatutSession::Planifiee->value)->whereDate('date_fin', '<', today())
                : $s->where('statut', $v)))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['domaine_id'] ?? null, fn ($q, $v) => $q->where('domaine_id', $v))
            ->when(isset($f['historique']), fn ($q) => $q->where('historique_importe', $f['historique'] === '1'))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('intitule', 'like', '%'.addcslashes($v, '%_').'%')->orWhere('reference', 'like', '%'.addcslashes($v, '%_').'%')))
            ->orderByDesc('annee')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('formations.index', ['formations' => $formations, 'f' => $f, 'limite' => $sid !== null, 'domaines' => Domaine::actifs()->get(),
            'annees' => ActionFormation::select('annee')->distinct()->orderByDesc('annee')->pluck('annee')]);
    }

    /** Nouvelle formation directe (sans demande), éventuellement sur le modèle d'une autre formation (?modele=). */
    public function create(Request $r): View
    {
        $formation = new ActionFormation(['type' => 'interne', 'modalite' => 'presentiel']);
        $session = new SessionFormation;
        if ($r->filled('modele')) {
            $modele = ActionFormation::with('session')->findOrFail((int) $r->query('modele'));
            $formation->fill($modele->only(ActionService::CHAMPS_ACTION));
            if ($modele->session) {
                $session->fill($modele->session->only(['heure_debut', 'heure_fin', 'duree_heures', 'lieu']));
                $session->setRelation('formateurs', $modele->session->formateurs);
            }
        }

        return view('formations.form', ['formation' => $formation, 'session' => $session] + $this->listes());
    }

    public function store(FormationRequest $r): RedirectResponse
    {
        $a = $this->actions->creerFormation($r->validated(), $r->user());

        return redirect()->route('formations.show', $a)->with('succes', 'Formation créée. Étape suivante : ajouter les participants.');
    }

    public function show(Request $r, ActionFormation $formation): View
    {
        $u = $r->user();
        abort_unless($formation->estVisiblePar($u), 403);
        $sid = ActionFormation::serviceLimite($u);
        $formation->load(['domaine', 'organisme', 'besoin.service', 'documents.categorie', 'session.formateurs.organisme', 'session.documents.categorie']);
        $s = $formation->session;
        $participants = collect();
        $agentsDisponibles = collect();
        $servicesAjout = collect();
        if ($s) {
            // Regroupés par service ; un responsable de structure ne voit que les participants de son service
            $participants = $s->participantsActifs()->with(['agent.service', 'attestation'])
                ->when($sid, fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('service_id', $sid)))->get()
                ->sortBy(fn ($i) => ($i->agent->service?->nom ?? '~').' '.$i->agent->nom.' '.$i->agent->prenom)->values();
            if ($u->aPermission('inscriptions.gerer') && ! in_array($s->statut, [StatutSession::Terminee, StatutSession::Annulee], true)) {
                // 29/10/2026 : au-delà de LISTE_MAX agents, recherche dans tout le personnel (route agents.choisir) au lieu de la liste complète
                $dispo = fn () => Agent::where('actif', true)->whereNotIn('id', $s->participantsActifs()->pluck('agent_id'));
                $agentsDisponibles = $dispo()->count() <= BesoinController::LISTE_MAX
                    ? $dispo()->with(['service', 'direction'])->orderBy('nom')->orderBy('prenom')->get(['id', 'matricule', 'nom', 'prenom', 'service_id', 'direction_id']) : null;
                $servicesAjout = Service::actifs()->whereHas('agents', fn ($q) => $q->where('actif', true))->with('direction')->get()
                    ->sortBy(fn ($sv) => ($sv->direction?->sigle ?: $sv->direction?->nom).' '.$sv->nom)->values();
            }
        }

        // Agents de la demande qui ne sont pas (ou plus) participants, avec la raison
        $nonInscrits = collect();
        if ($s && $formation->besoin && $u->aPermission('inscriptions.gerer')) {
            $inscrits = $s->participantsActifs()->pluck('agent_id');
            $nonInscrits = $formation->besoin->agents()->with('service')->whereNotIn('agents.id', $inscrits)->orderBy('nom')->get()
                ->when($sid, fn ($c) => $c->where('service_id', $sid))
                ->map(fn ($ag) => ['agent' => $ag, 'raison' => $this->raisonNonInscrit($ag, $s)]);
        }

        // Après la formation (1.9.0) : évaluations, rapport de réalisation, attestations des formateurs externes
        $apres = null;
        if ($s && $s->statut === StatutSession::Terminee) {
            $ev = app(EvaluationService::class);
            $apres = ['chaud' => $ev->syntheseChaud($s), 'froid' => $ev->syntheseFroid($s),
                'attestationsIntervenants' => $s->attestationsIntervenants()->get()->keyBy('formateur_id')];
        }

        return view('formations.show', ['a' => $formation, 's' => $s, 'participants' => $participants, 'limite' => $sid !== null, 'nonInscrits' => $nonInscrits, 'agentsDisponibles' => $agentsDisponibles, 'servicesAjout' => $servicesAjout,
            'categoriesDoc' => Nomenclature::deType('categorie_document')->get(), 'apres' => $apres,
            'impressions' => $u->aPermission('attestations.emettre') ? Attestation::impressions($participants->pluck('attestation.id')) : [],
            'convocation' => $s ? app(ConvocationService::class)->etat($s) : null]);
    }

    private function raisonNonInscrit(Agent $ag, SessionFormation $s): string
    {
        if (! $ag->actif) {
            return 'agent inactif';
        }
        if ($s->inscriptions()->where('agent_id', $ag->id)->where('statut', 'desiste')->exists()) {
            return 'retiré de la formation';
        }
        if ($code = app(InscriptionService::class)->chevauchement($ag->id, $s)) {
            $autre = SessionFormation::with('action')->where('code', $code)->first();

            return 'déjà inscrit à « '.($autre?->action?->intitule ?? $code).' » ('.$code.') sur des dates qui se chevauchent';
        }

        return 'non inscrit';
    }

    /** Étape « Participants » : ajout d'agents (sélection individuelle ou tout un service). */
    public function ajouterParticipants(Request $r, ActionFormation $formation, InscriptionService $inscriptions): RedirectResponse
    {
        $d = $r->validate(['agents' => ['nullable', 'array', 'max:2000'], 'agents.*' => ['integer', 'exists:agents,id'], 'service_id' => ['nullable', 'integer', 'exists:services,id']]);
        $ids = $d['agents'] ?? [];
        if (! empty($d['service_id'])) {
            $ids = array_merge($ids, Agent::where('service_id', $d['service_id'])->where('actif', true)->pluck('id')->all());
        }
        if ($ids === []) {
            return back()->with('erreur', 'Choisissez au moins un agent (ou un service).');
        }
        $s = $formation->session()->firstOrFail();
        $res = $inscriptions->inscrire($s, $ids, $r->user());
        $msg = count($res['inscrits']).' participant(s) ajouté(s).';
        // Formation déjà convoquée (28/10/2026) : les nouveaux participants reçoivent aussitôt leur convocation
        // (sauf si une convocation rectificative est en attente : la RH l'envoie alors à tous, depuis la fiche)
        $conv = app(ConvocationService::class);
        $s->refresh();
        if ($res['inscrits'] && $s->convoquee_le && ! $conv->etat($s)['a_reconvoquer'] && ($texte = $conv->convoquerAussitot($s, $r->user(), true))) {
            $msg .= ' '.$texte;
        }

        return back()->with('succes', $msg)->with('avertissements', $res['refus']);
    }

    public function retirerParticipant(Request $r, Inscription $inscription, InscriptionService $inscriptions): RedirectResponse
    {
        $inscriptions->retirer($inscription, $r->user());

        return back()->with('succes', "{$inscription->agent->nom_complet} est retiré(e) de la formation.");
    }

    /** Étape de fin : la RH coche les participants qui ont suivi la formation. */
    public function terminer(Request $r, ActionFormation $formation): RedirectResponse
    {
        $d = $r->validate(['suivi' => ['nullable', 'array'], 'suivi.*' => ['integer']]);
        $s = $formation->session()->firstOrFail();
        $correction = $s->statut === StatutSession::Terminee;
        $this->actions->terminer($s, $d['suivi'] ?? [], $r->user());

        return back()->with('succes', $correction ? 'Suivi corrigé.' : 'Formation terminée. Étape suivante : émettre les attestations.');
    }

    public function edit(ActionFormation $formation): View
    {
        $formation->load(['session', 'besoin']);

        return view('formations.form', ['formation' => $formation, 'session' => $formation->session ?? new SessionFormation] + $this->listes());
    }

    public function update(FormationRequest $r, ActionFormation $formation): RedirectResponse
    {
        $d = $r->validated();
        $this->actions->modifierFormation($formation, $d, $r->user(), mb_substr((string) ($d['motif'] ?? ''), 0, 500) ?: null);

        return redirect()->route('formations.show', $formation)->with('succes', 'Formation mise à jour.');
    }

    private function listes(): array
    {
        return ['domaines' => Domaine::actifs()->get(), 'organismes' => Organisme::where('actif', true)->orderBy('raison_sociale')->get(),
            'formateurs' => Formateur::with('organisme')->where('actif', true)->orderBy('type')->orderBy('nom')->get()];
    }
}
