<?php

namespace App\Http\Controllers;

use App\Enums\StatutSession;
use App\Models\ActionFormation;
use App\Models\AttestationIntervenant;
use App\Models\EvaluationAcquis;
use App\Models\Formateur;
use App\Models\SessionFormation;
use App\Services\Audit;
use App\Services\EvaluationService;
use App\Services\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Après la formation (1.9.0) : évaluations à chaud et à 3–6 mois, rapport de réalisation,
 * attestations d'intervention des formateurs externes. Les règles sont dans EvaluationService.
 */
class ApresFormationController extends Controller
{
    public function __construct(private EvaluationService $evaluations) {}

    private function session(ActionFormation $a): SessionFormation
    {
        abort_unless($a->estVisiblePar(request()->user()), 403);

        return $a->session()->firstOrFail();
    }

    // ---- Évaluation à chaud (RH) : le participant a-t-il compris ? ----------------------------------------------

    public function chaud(ActionFormation $formation): View
    {
        $s = $this->session($formation);
        abort_unless($s->statut === StatutSession::Terminee, 404);

        return view('evaluations.chaud', ['a' => $formation, 's' => $s, 'participants' => $this->evaluations->participantsChaud($s)]);
    }

    public function enregistrerChaud(Request $r, ActionFormation $formation): RedirectResponse
    {
        $s = $this->session($formation);
        $d = $r->validate([
            'lignes' => ['required', 'array', 'max:2000'],
            'lignes.*.resultat' => ['nullable', Rule::in(array_keys(EvaluationAcquis::REPONSES))],
            'lignes.*.note' => ['nullable', 'numeric', 'between:0,'.EvaluationAcquis::NOTE_MAX],
            'lignes.*.commentaire' => ['nullable', 'string', 'max:1000'],
        ], ['lignes.*.note.between' => 'La note va de 0 à '.EvaluationAcquis::NOTE_MAX.'.', 'lignes.*.note.numeric' => 'La note doit être un nombre.']);
        [$n, $eff] = $this->evaluations->enregistrerChaud($s, $d['lignes'], $r->user());

        return redirect()->to(route('formations.show', $formation).'#apres-formation')
            ->with('succes', "Compréhension enregistrée pour {$n} participant(s)".($eff ? ", {$eff} réponse(s) effacée(s)" : '').'.');
    }

    public function delaiFroid(Request $r, ActionFormation $formation): RedirectResponse
    {
        $d = $r->validate(['mois' => ['required', 'integer', Rule::in(SessionFormation::DELAIS_FROID)]]);
        $s = $this->session($formation);
        $this->evaluations->changerDelaiFroid($s, (int) $d['mois'], $r->user());

        return back()->with('succes', "La mise en pratique sera évaluée {$d['mois']} mois après la formation, à partir du {$s->dateEvaluationFroid()->format('d/m/Y')}.");
    }

    // ---- Évaluation à 3–6 mois (responsable de structure ; RH pour tous) : applique-t-il ce qu'il a appris ? -------------

    /** Liste par formation : à faire (date atteinte, agents non évalués), à venir, faites. */
    public function index(Request $r): View
    {
        $f = $r->validate(['etat' => ['nullable', Rule::in(['a_faire', 'a_venir', 'faites'])]]);
        $etat = $f['etat'] ?? 'a_faire';
        $formations = $this->evaluations->inscriptionsFroid($r->user())->get()->groupBy('session_id')->map(function ($ins) {
            $s = $ins->first()->session;
            $restants = $ins->filter(fn ($i) => ! $i->evaluationsImpact->first()?->resultat_atteint)->count();

            return (object) ['s' => $s, 'agents' => $ins->count(), 'restants' => $restants, 'a_partir_du' => $s->dateEvaluationFroid(),
                'etat' => $restants === 0 ? 'faites' : (today()->lt($s->dateEvaluationFroid()) ? 'a_venir' : 'a_faire')];
        });
        $compteurs = collect(['a_faire', 'a_venir', 'faites'])->mapWithKeys(fn ($e) => [$e => $formations->where('etat', $e)->count()])->all();
        $liste = $formations->where('etat', $etat)->sortBy(fn ($x) => $x->a_partir_du)->values();

        return view('evaluations.index', ['etat' => $etat, 'liste' => $liste, 'compteurs' => $compteurs]);
    }

    public function froid(Request $r, ActionFormation $formation): View
    {
        $s = $this->session($formation);
        abort_unless($s->statut === StatutSession::Terminee, 404);
        $agents = $this->evaluations->agentsFroid($s, $r->user());
        abort_if($agents->isEmpty(), 404);

        return view('evaluations.froid', ['a' => $formation->loadMissing('besoin'), 's' => $s, 'agents' => $agents]);
    }

    public function enregistrerFroid(Request $r, ActionFormation $formation): RedirectResponse
    {
        $s = $this->session($formation);
        $d = $r->validate([
            'lignes' => ['required', 'array', 'max:2000'],
            'lignes.*.resultat' => ['nullable', Rule::in(array_keys(EvaluationAcquis::REPONSES))],
            'lignes.*.commentaire' => ['nullable', 'string', 'max:1000'],
        ]);
        $n = $this->evaluations->enregistrerFroid($s, $d['lignes'], $r->user());

        return redirect()->route('evaluations.index')->with('succes', "Mise en pratique enregistrée pour {$n} agent(s).");
    }

    // ---- Rapport de réalisation (RH) ----------------------------------------------------------------------------

    public function rapport(ActionFormation $formation): View
    {
        $s = $this->session($formation);
        abort_unless($s->statut === StatutSession::Terminee, 404);

        return view('evaluations.rapport', ['a' => $formation, 's' => $s] + $this->donneesRapport($formation, $s));
    }

    public function enregistrerRapport(Request $r, ActionFormation $formation): RedirectResponse
    {
        $s = $this->session($formation);
        $d = $r->validate(['rapport_deroulement' => ['required', 'string', 'max:10000'], 'rapport_difficultes' => ['nullable', 'string', 'max:10000'],
            'rapport_recommandations' => ['nullable', 'string', 'max:10000']], [], ['rapport_deroulement' => 'déroulement']);
        $this->evaluations->enregistrerRapport($s, $d, $r->user());

        return redirect()->route('formations.show', $formation)->with('succes', 'Rapport de réalisation enregistré. Vous pouvez le télécharger en PDF.');
    }

    public function rapportPdf(ActionFormation $formation, ExportService $export): Response
    {
        $s = $this->session($formation);
        abort_unless($s->statut === StatutSession::Terminee, 404);
        Audit::enregistrer('export.rapport_realisation', $formation, "Rapport de réalisation de la formation {$formation->reference} téléchargé");

        return $export->pdf('pdf.rapport-realisation', ['a' => $formation, 's' => $s] + $this->donneesRapport($formation, $s), 'rapport-realisation-'.$formation->reference);
    }

    private function donneesRapport(ActionFormation $a, SessionFormation $s): array
    {
        $a->loadMissing(['organisme', 'besoin.service']);
        $s->loadMissing(['formateurs.organisme', 'redacteurRapport']);
        // Participants : inscrits (non retirés) ou dont le résultat a été noté à la fin de la formation
        $inscrits = $s->inscriptions()->with('agent.service')->where(fn ($q) => $q->whereIn('statut', SessionFormation::STATUTS_OCCUPANT_PLACE)->orWhereNotNull('resultat'))->get();
        $suivis = $inscrits->where('resultat', 'achevee');

        return [
            'inscrits' => $inscrits->count(), 'suivis' => $suivis->count(),
            'parService' => $inscrits->groupBy(fn ($i) => $i->agent->service?->nom ?? 'Sans service')->map(fn ($g) => [$g->count(), $g->where('resultat', 'achevee')->count()])->sortKeys(),
            'chaud' => $this->evaluations->syntheseChaud($s), 'froid' => $this->evaluations->syntheseFroid($s),
        ];
    }

    // ---- Attestation d'intervention (formateur externe) ---------------------------------------------------------

    public function attester(Request $r, ActionFormation $formation, Formateur $formateur): RedirectResponse
    {
        $s = $this->session($formation);
        $att = $this->evaluations->attesterIntervenant($s, $formateur, $r->user());

        return redirect()->to(route('formations.show', $formation).'#apres-formation')
            ->with('succes', "Attestation d'intervention {$att->reference} de {$formateur->nom_complet} émise : vous pouvez la télécharger.");
    }

    public function pdfIntervenant(AttestationIntervenant $attestation, ExportService $export): Response
    {
        $attestation->load(['formateur.organisme', 'session.action']);
        abort_unless($attestation->session->action->estVisiblePar(request()->user()), 403);
        $participants = $attestation->session->inscriptions()->where('resultat', 'achevee')->count();

        return $export->pdf('pdf.attestation-intervenant', ['att' => $attestation, 's' => $attestation->session, 'participants' => $participants], 'attestation-intervention-'.$attestation->reference);
    }
}
