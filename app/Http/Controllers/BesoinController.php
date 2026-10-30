<?php

namespace App\Http\Controllers;

use App\Enums\StatutBesoin;
use App\Http\Requests\BesoinRequest;
use App\Models\Agent;
use App\Models\AnneeReference;
use App\Models\BesoinFormation;
use App\Models\Nomenclature;
use App\Models\Organisme;
use App\Models\Service;
use App\Services\ActionService;
use App\Services\AideDecisionService;
use App\Services\BesoinService;
use App\Services\ConvocationService;
use App\Services\SuppressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Module C — Recensement des besoins (cloisonnement par service via policy). */
class BesoinController extends Controller
{
    /**
     * Nombre d'agents au-delà duquel le formulaire propose une recherche plutôt que la liste complète.
     * 29/10/2026 : la RH veut voir la liste de tout le personnel (3 671 agents) : la liste complète est gardée jusqu'à 6 000.
     */
    public const LISTE_MAX = 6000;

    public function __construct(private BesoinService $service) {}

    public function index(Request $r): View
    {
        $this->authorize('viewAny', BesoinFormation::class);
        $f = $r->validate(['annee' => ['nullable', 'integer'], 'statut' => ['nullable', Rule::in(array_keys(BesoinFormation::optionsFiltre()))], 'service_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100']]);
        $etatFormation = fn ($q, string $statut) => $q->where('statut', StatutBesoin::Organisee->value)
            ->whereHas('formation.session', fn ($s) => $s->where('statut', $statut));
        $besoins = BesoinFormation::visiblesPar($r->user())->with(['service.direction', 'auteur', 'formation.session'])->withCount('agents')
            ->when($f['annee'] ?? null, fn ($q, $v) => $q->where('annee', $v))
            ->when($f['statut'] ?? null, fn ($q, $v) => match ($v) {
                'formation_terminee' => $etatFormation($q, 'terminee'),
                'formation_annulee' => $etatFormation($q, 'annulee'),
                // « Formation programmée » : formation ni terminée ni annulée
                StatutBesoin::Organisee->value => $q->where('statut', $v)->whereDoesntHave('formation.session', fn ($s) => $s->whereIn('statut', ['terminee', 'annulee'])),
                default => $q->where('statut', $v),
            })
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('intitule', 'like', '%'.addcslashes($v, '%_').'%')->orWhere('reference', 'like', '%'.addcslashes($v, '%_').'%')))
            ->latest('id')->paginate(25)->withQueryString();

        return view('besoins.index', ['besoins' => $besoins, 'f' => $f, 'annees' => AnneeReference::orderByDesc('annee')->pluck('annee'),
            'services' => $r->user()->aPermission('besoins.voir_tous') ? Service::actifs()->get() : collect()]);
    }

    public function create(Request $r): View
    {
        $this->authorize('create', BesoinFormation::class);

        return view('besoins.form', ['besoin' => new BesoinFormation(['service_id' => $r->user()->service_id])] + $this->listes($r));
    }

    public function store(BesoinRequest $r): RedirectResponse
    {
        $b = $this->service->creer($r->donnees(), $r->user(), $this->agentsAutorises($r));

        return redirect()->route('besoins.show', $b)->with('succes', 'Demande envoyée à la RH.');
    }

    public function show(Request $r, BesoinFormation $besoin): View
    {
        $this->authorize('view', $besoin);
        $besoin->load(['service.direction', 'priorite', 'typeFormation', 'domaine', 'auteur', 'agents', 'decisions.user', 'documents.categorie', 'formation.session']);

        // Aide à la décision (2.1.0) : seulement pour qui décide des demandes
        $aide = $r->user()->aPermission('besoins.instruire') ? app(AideDecisionService::class)->signaux($besoin) : [];

        return view('besoins.show', ['b' => $besoin, 'aide' => $aide, 'categoriesDoc' => Nomenclature::deType('categorie_document')->get(),
            'organismes' => $r->user()->aPermission('actions.gerer') ? Organisme::where('actif', true)->orderBy('raison_sociale')->get() : collect()]);
    }

    public function edit(Request $r, BesoinFormation $besoin): View
    {
        $this->authorize('update', $besoin);

        return view('besoins.form', ['besoin' => $besoin->load('agents')] + $this->listes($r));
    }

    public function update(BesoinRequest $r, BesoinFormation $besoin): RedirectResponse
    {
        $this->authorize('update', $besoin);
        $this->service->modifier($besoin, $r->donnees(), $r->user(), $this->agentsAutorises($r));
        $this->service->transition($besoin, 'soumission', $r->user());

        return redirect()->route('besoins.show', $besoin)->with('succes', 'Demande complétée et renvoyée à la RH.');
    }

    public function destroy(Request $r, BesoinFormation $besoin): RedirectResponse
    {
        $this->authorize('delete', $besoin);
        app(SuppressionService::class)->supprimerBesoin($besoin, $r->user());

        return redirect()->route('besoins.index')->with('succes', "Demande {$besoin->reference} supprimée.");
    }

    public function transition(Request $r, BesoinFormation $besoin): RedirectResponse
    {
        $d = $r->validate(['action' => ['required', 'in:soumission,demande_complement,rejet'], 'commentaire' => ['nullable', 'string', 'max:2000']]);
        $this->authorize($d['action'] === 'soumission' ? 'update' : 'instruire', $besoin);
        $this->service->transition($besoin, $d['action'], $r->user(), $d['commentaire'] ?? null);

        return back()->with('succes', 'Décision enregistrée.');
    }

    /**
     * Accepter = programmer (version 1.5.0) : la RH fixe les dates, le lieu et l'organisme ;
     * la formation est créée et les agents de la demande y sont inscrits.
     */
    public function accepter(Request $r, BesoinFormation $besoin, ActionService $actions): RedirectResponse
    {
        $this->authorize('instruire', $besoin);
        $d = $r->validate(['date_debut' => ['required', 'date'], 'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'lieu' => ['nullable', 'string', 'max:255'], 'organisme_id' => ['nullable', 'integer', 'exists:organismes,id'],
            'commentaire' => ['nullable', 'string', 'max:2000'], 'convoquer' => ['nullable', 'boolean']], [], ['date_debut' => 'date de début', 'date_fin' => 'date de fin']);
        if (! in_array($besoin->statut, [StatutBesoin::Soumise, StatutBesoin::Acceptee], true)) {
            return back()->with('erreur', "Action impossible : la demande est « {$besoin->statut->libelle()} ».");
        }
        $a = $actions->accepterEtProgrammer($besoin, $d, $r->user(), $d['commentaire'] ?? null);
        $refus = $a->getAttribute('refus_inscription') ?? [];
        $a->offsetUnset('refus_inscription');
        // Convocation des agents dès l'acceptation (28/10/2026) : case « Convoquer aussitôt les agents », cochée par défaut
        $convocation = $r->boolean('convoquer', true) ? app(ConvocationService::class)->convoquerAussitot($a->session()->firstOrFail(), $r->user(), false, $besoin->auteur_id) : null;

        return redirect()->route('formations.show', $a)->with('succes', ($refus
            ? 'Demande acceptée : la formation est programmée. Attention, '.count($refus).' agent(s) de la demande n\'ont pas pu être inscrits (voir ci-dessous).'
            : 'Demande acceptée : la formation est programmée et les agents de la demande sont inscrits.').($convocation ? ' '.$convocation : ''))->with('avertissements', $refus);
    }

    /** Agents à former : limités au périmètre visible par l'utilisateur ; au moins un. */
    private function agentsAutorises(BesoinRequest $r): array
    {
        $ids = $r->validated()['agents'] ?? [];
        $autorises = $ids ? Agent::visiblesPar($r->user())->where('actif', true)->whereIn('id', $ids)->pluck('id')->all() : [];
        if ($autorises === []) {
            throw ValidationException::withMessages(['agents' => 'Cochez au moins un agent à former.']);
        }

        return $autorises;
    }

    private function listes(Request $r): array
    {
        $u = $r->user();
        $visibles = fn () => Agent::visiblesPar($u)->where('actif', true);
        $nb = $visibles()->count();
        // 29/10/2026 : liste complète de tous les agents visibles (l'ancienne limite à 1 000 cachait la plupart d'entre eux) ;
        // au-delà de LISTE_MAX seulement, recherche dans tout le personnel côté serveur.
        $besoin = $r->route('besoin');
        $ids = collect(old('agents', $besoin instanceof BesoinFormation ? $besoin->agents()->pluck('agents.id')->all() : []))->map(fn ($v) => (int) $v)->all();

        return [
            'services' => $u->aPermission('besoins.creer_tous_services') ? Service::actifs()->with('direction')->get() : collect(),
            'nbAgents' => $nb,
            'agents' => $nb <= self::LISTE_MAX ? $visibles()->with(['service', 'direction'])->orderBy('nom')->orderBy('prenom')->get(['id', 'matricule', 'nom', 'prenom', 'service_id', 'direction_id']) : null,
            'agentsChoisis' => $ids ? $visibles()->whereIn('id', $ids)->orderBy('nom')->orderBy('prenom')->get(['id', 'matricule', 'nom', 'prenom']) : collect(),
        ];
    }
}
