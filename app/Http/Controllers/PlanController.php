<?php

namespace App\Http\Controllers;

use App\Models\ActionFormation;
use App\Models\AnneeReference;
use App\Models\BesoinFormation;
use App\Models\Organisme;
use App\Models\PlanAnnuel;
use App\Models\PlanLigne;
use App\Models\Service;
use App\Services\Audit;
use App\Services\ConvocationService;
use App\Services\ExportService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan annuel (1.7.0). La RH gère le plan ; un responsable de structure le consulte, limité aux lignes de son service.
 */
class PlanController extends Controller
{
    public function __construct(private PlanService $service) {}

    public function index(Request $r): View
    {
        $annee = $this->annee($r);
        $plan = PlanAnnuel::with('validePar')->where('annee', $annee)->first();
        $lignes = $this->lignes($plan, $r);
        $etats = $lignes->groupBy(fn ($l) => $l->etat())->map->count();

        return view('plan.index', ['annee' => $annee, 'plan' => $plan, 'lignes' => $lignes,
            'parTrimestre' => $lignes->groupBy(fn ($l) => $l->trimestre())->sortKeys(),
            'etats' => $etats, 'annees' => $this->annees(), 'gerer' => $r->user()->aPermission('actions.gerer'),
            'services' => Service::optionsParDirection(), 'organismes' => Organisme::where('actif', true)->orderBy('raison_sociale')->pluck('raison_sociale', 'id')]);
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $this->donnees($r) + $r->validate(['annee' => ['required', 'integer', 'between:2000,2100']]);
        $l = $this->service->ajouterLigne((int) $d['annee'], $d, $r->user());

        return redirect()->route('plan.index', ['annee' => $d['annee']])->with('succes', "« {$l->intitule} » ajoutée au plan {$d['annee']}.");
    }

    public function update(Request $r, PlanLigne $ligne): RedirectResponse
    {
        $this->service->modifierLigne($ligne, $this->donnees($r));

        return back()->with('succes', 'Ligne du plan modifiée.');
    }

    public function destroy(Request $r, PlanLigne $ligne): RedirectResponse
    {
        $motif = $r->validate(['motif' => [$ligne->besoin_id ? 'required' : 'nullable', 'string', 'max:1000']], ['motif.required' => 'Indiquez le motif : il sera transmis au service demandeur.'])['motif'] ?? null;
        $this->service->retirerLigne($ligne, $r->user(), $motif);

        return back()->with('succes', "« {$ligne->intitule} » retirée du plan.".($ligne->getOriginal('besoin_id') ? ' La demande redevient « Envoyée ».' : ''));
    }

    public function programmer(Request $r, PlanLigne $ligne): RedirectResponse
    {
        $d = $r->validate(['date_debut' => ['required', 'date'], 'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'lieu' => ['nullable', 'string', 'max:255'], 'organisme_id' => ['nullable', 'integer', 'exists:organismes,id'], 'convoquer' => ['nullable', 'boolean']],
            [], ['date_debut' => 'date de début', 'date_fin' => 'date de fin']);
        [$a, $refus] = $this->service->programmer($ligne, $d, $r->user());
        // Convocation des agents inscrits dès la programmation (28/10/2026), si la case est cochée
        $convocation = $r->boolean('convoquer', true) ? app(ConvocationService::class)->convoquerAussitot($a->session()->firstOrFail(), $r->user(), false, $a->besoin?->auteur_id) : null;

        return redirect()->route('formations.show', $a)->with('succes', 'Formation programmée depuis le plan annuel.'
            .($refus ? ' Attention, '.count($refus).' agent(s) de la demande n\'ont pas pu être inscrits.' : '').($convocation ? ' '.$convocation : ''))->with('avertissements', $refus);
    }

    private function donnees(Request $r): array
    {
        return $r->validate([
            'intitule' => ['required', 'string', 'max:255'],
            'periode' => ['required', Rule::in(array_keys(PlanLigne::PERIODES))],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'nb_agents' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'type' => ['nullable', 'in:interne,externe,mixte'],
            'organisme_id' => ['nullable', 'integer', 'exists:organismes,id'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ], [], ['intitule' => 'formation', 'periode' => 'période', 'nb_agents' => 'nombre d\'agents']);
    }

    public function validerPlan(Request $r, int $annee): RedirectResponse
    {
        $c = $r->validate(['commentaire' => ['nullable', 'string', 'max:1000']])['commentaire'] ?? null;
        $this->service->valider($this->service->plan($annee), $r->user(), $c);

        return back()->with('succes', "Plan annuel {$annee} validé. Il peut encore être complété.");
    }

    public function export(Request $r, int $annee, string $format, ExportService $export): Response
    {
        $plan = PlanAnnuel::with('validePar')->where('annee', $annee)->first();
        $lignes = $this->lignes($plan, $r);
        Audit::enregistrer('export.plan', 'plan', "Export du plan annuel {$annee} ($format)");
        if ($format === 'pdf') {
            return $export->pdf('pdf.plan', ['annee' => $annee, 'plan' => $plan, 'parTrimestre' => $lignes->groupBy(fn ($l) => $l->trimestre())->sortKeys()],
                "plan-annuel-$annee", 'landscape');
        }

        return $export->excel("Plan annuel de formation $annee", ['Période', 'Formation', 'Service', 'Agents prévus', 'Type', 'Organisme', 'Demande', 'État', 'Formation programmée', 'Dates'],
            $lignes->map(fn ($l) => [$l->libellePeriode(), $l->intitule, $l->service?->nom, $l->nb_agents, $l->type ? ucfirst($l->type) : null, $l->organisme?->raison_sociale,
                $l->besoin?->reference, PlanLigne::ETATS[$l->etat()], $l->formation?->reference,
                $l->formation?->session ? $l->formation->session->date_debut->format('d/m/Y').' – '.$l->formation->session->date_fin->format('d/m/Y') : null])->all(),
            "plan-annuel-$annee", [], $plan?->estValide() ? 'Plan validé le '.$plan->valide_le->format('d/m/Y').' par '.$plan->validePar?->name.'.' : 'Plan en préparation (non validé).');
    }

    /** Lignes visibles : toutes pour la RH ; pour un responsable de structure, celles de son service ou de ses demandes. */
    private function lignes(?PlanAnnuel $plan, Request $r): Collection
    {
        if (! $plan) {
            return collect();
        }
        $sid = ActionFormation::serviceLimite($r->user());
        $ordre = array_flip(array_keys(PlanLigne::PERIODES));

        return $plan->lignes()->with(['service', 'organisme', 'besoin', 'formation.session'])
            ->when($sid, fn ($q) => $q->where(fn ($w) => $w->where('service_id', $sid)->orWhereHas('besoin', fn ($b) => $b->where('service_id', $sid))))
            ->get()->sortBy(fn ($l) => sprintf('%02d %s', $ordre[$l->periode] ?? 99, $l->intitule))->values();
    }

    private function annee(Request $r): int
    {
        $a = (int) ($r->validate(['annee' => ['nullable', 'integer', 'between:2000,2100']])['annee'] ?? 0);

        return $a ?: (int) now()->year;
    }

    private function annees(): array
    {
        return collect([now()->year, now()->year + 1])->merge(PlanAnnuel::pluck('annee'))->merge(AnneeReference::pluck('annee'))
            ->map(fn ($a) => (int) $a)->unique()->sortDesc()->values()->all();
    }

    /** Inscription d'une demande au plan depuis sa fiche (« Accepter et inscrire au plan »). */
    public function inscrireDemande(Request $r, BesoinFormation $besoin): RedirectResponse
    {
        $this->authorize('instruire', $besoin);
        $d = $r->validate(['annee' => ['required', 'integer', 'between:2000,2100'], 'periode' => ['required', Rule::in(array_keys(PlanLigne::PERIODES))],
            'commentaire' => ['nullable', 'string', 'max:1000']], [], ['periode' => 'période']);
        $this->service->inscrireDemande($besoin, (int) $d['annee'], $d['periode'], $r->user(), $d['commentaire'] ?? null);

        return redirect()->route('plan.index', ['annee' => $d['annee']])->with('succes', "Demande {$besoin->reference} acceptée et inscrite au plan {$d['annee']}.");
    }
}
