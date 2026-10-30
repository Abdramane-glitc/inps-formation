<?php

namespace Tests\Feature;

use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\AttestationIntervenant;
use App\Models\EvaluationAcquis;
use App\Models\EvaluationImpact;
use App\Models\Formateur;
use App\Models\Inscription;
use App\Models\Organisme;
use App\Models\SessionFormation;
use App\Models\User;
use App\Services\ActionService;
use App\Services\BesoinService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 1.9.0 : après la formation — évaluation à chaud (RH, fiches papier), évaluation à 3–6 mois (responsable de structure),
 * rapport de réalisation, attestation d'intervention des formateurs externes, rappel automatique.
 */
class ApresFormationTest extends TestCase
{
    private User $rh;

    private User $chef;

    private SessionFormation $s;

    /** @var array<int, Inscription> [0] service 1 a suivi, [1] service 2 a suivi, [2] service 1 n'a pas suivi */
    private array $ins;

    private Formateur $externe;

    private Formateur $interne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rh = $this->utilisateur('responsable_drh');
        $this->chef = $this->utilisateur('chef_service');
        $a1 = Agent::create(['matricule' => 'AP1', 'nom' => 'COULIBALY', 'prenom' => 'Awa', 'service_id' => $this->service1->id]);
        $a2 = Agent::create(['matricule' => 'AP2', 'nom' => 'DIARRA', 'prenom' => 'Moussa', 'service_id' => $this->service2->id]);
        $a3 = Agent::create(['matricule' => 'AP3', 'nom' => 'KEITA', 'prenom' => 'Sira', 'service_id' => $this->service1->id]);
        $org = Organisme::create(['raison_sociale' => 'Cabinet Essai', 'actif' => true]);
        $this->externe = Formateur::create(['type' => 'externe', 'organisme_id' => $org->id, 'nom' => 'TRAORE', 'prenom' => 'Ali', 'actif' => true]);
        $this->interne = Formateur::create(['type' => 'interne', 'agent_id' => $a3->id, 'nom' => 'KEITA', 'prenom' => 'Sira', 'actif' => true]);

        $b = app(BesoinService::class)->creer(['intitule' => 'Tableur', 'contexte' => 'Erreurs de calcul', 'causes' => ['connaissances'],
            'justification_formation' => 'Jamais formés', 'resultat_attendu' => 'Tenir seul le relevé mensuel sans erreur'], $this->chef, [$a1->id, $a2->id, $a3->id]);
        $a = app(ActionService::class)->accepterEtProgrammer($b, ['date_debut' => today()->subDays(3)->toDateString(), 'date_fin' => today()->subDays(2)->toDateString(), 'organisme_id' => $org->id], $this->rh);
        ActionFormation::whereKey($a->id)->update(['type' => 'mixte']);
        $this->s = $a->session()->firstOrFail();
        app(ActionService::class)->affecterFormateurs($this->s, [$this->externe->id, $this->interne->id]);
        $this->ins = $this->s->inscriptions()->orderBy('agent_id')->get()->all();
        app(ActionService::class)->terminer($this->s, [$this->ins[0]->id, $this->ins[1]->id], $this->rh);
        $this->s->refresh();
    }

    /** 2.2.0 : à chaud, la RH indique pour chaque participant s'il a compris (oui / en partie / non), sur un seul écran. */
    public function test_la_rh_indique_qui_a_compris(): void
    {
        $a = $this->s->action;
        // Seuls ceux qui ont suivi sont proposés ; le responsable de structure n'y a pas accès
        $this->actingAs($this->rh)->get(route('evaluations.chaud', $a))->assertOk()->assertSee('Ont-ils compris la formation ?')
            ->assertSee('COULIBALY Awa')->assertSee('DIARRA Moussa')->assertDontSee('KEITA Sira');
        $this->actingAs($this->chef)->get(route('evaluations.chaud', $a))->assertForbidden();
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertSee('Indiquer qui a compris')->assertDontSee('Fiches d&#039;évaluation', false);

        // Note hors barème refusée ; note ou commentaire sans réponse refusés
        $this->actingAs($this->rh)->post(route('evaluations.chaud.enregistrer', $a), ['lignes' => [$this->ins[0]->id => ['resultat' => 'oui', 'note' => 25]]])->assertSessionHasErrors();
        $this->actingAs($this->rh)->post(route('evaluations.chaud.enregistrer', $a), ['lignes' => [$this->ins[0]->id => ['note' => 12]]])
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'indiquez s\'il a compris'));

        $this->actingAs($this->rh)->post(route('evaluations.chaud.enregistrer', $a), ['lignes' => [
            $this->ins[0]->id => ['resultat' => 'oui', 'note' => '16', 'commentaire' => 'Très à l\'aise'],
            $this->ins[1]->id => ['resultat' => 'partiel'],
            $this->ins[2]->id => ['resultat' => 'non'], // n'a pas suivi : ignoré
        ]])->assertSessionHasNoErrors()->assertSessionHas('succes', 'Compréhension enregistrée pour 2 participant(s).');
        $this->assertSame(['oui', 'partiel'], EvaluationAcquis::orderBy('id')->pluck('resultat')->all());
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertSee('2 sur 2 participant(s) : Oui 1, En partie 1, Non 0')->assertSee('note moyenne 16,0/20');

        // Vider une ligne efface la réponse saisie par erreur
        $this->actingAs($this->rh)->post(route('evaluations.chaud.enregistrer', $a), ['lignes' => [$this->ins[1]->id => ['resultat' => '', 'commentaire' => '']]])->assertSessionHas('succes');
        $this->assertSame(1, EvaluationAcquis::count());

        // Pas avant la fin de la formation
        $autre = $this->nouvelleFormation($this->rh);
        $this->actingAs($this->rh)->get(route('evaluations.chaud', $autre->action))->assertNotFound();
    }

    /** 2.2.0 : à froid, le responsable de structure indique pour ses agents s'ils appliquent ce qu'ils ont appris, sur un seul écran. */
    public function test_le_chef_de_service_indique_qui_applique(): void
    {
        $a = $this->s->action;
        $chef2 = $this->utilisateur('chef_service', $this->service2);
        // Avant la date : « à venir », enregistrement refusé
        $this->actingAs($this->chef)->get(route('evaluations.index', ['etat' => 'a_venir']))->assertOk()->assertSee('Tableur')->assertSee('1 à évaluer sur 1');
        $this->actingAs($this->chef)->post(route('evaluations.froid.enregistrer', $a), ['lignes' => [$this->ins[0]->id => ['resultat' => 'oui']]])
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'à partir du'));

        // La RH choisit 3 mois ; la formation s'est terminée il y a 4 mois
        $this->actingAs($this->rh)->post(route('evaluations.delai', $a), ['mois' => 7])->assertSessionHasErrors('mois');
        $this->actingAs($this->rh)->post(route('evaluations.delai', $a), ['mois' => 3])->assertSessionHas('succes');
        DB::table('sessions_formation')->where('id', $this->s->id)->update(['date_debut' => today()->subMonths(4)->subDay(), 'date_fin' => today()->subMonths(4)]);

        // Le chef ne voit que ses agents ; le résultat attendu de la demande est rappelé
        $this->actingAs($this->chef)->get(route('evaluations.index'))->assertOk()->assertSee('Tableur')->assertSee('Évaluer');
        $this->actingAs($this->chef)->get(route('evaluations.froid', $a))->assertOk()->assertSee('Applique-t-il ce qu\'il a appris ?', false)
            ->assertSee('Tenir seul le relevé mensuel sans erreur')->assertSee('COULIBALY Awa')->assertDontSee('DIARRA Moussa')->assertDontSee('KEITA Sira');
        // Une réponse pour un agent d'un autre service est ignorée
        $this->actingAs($this->chef)->post(route('evaluations.froid.enregistrer', $a), ['lignes' => [
            $this->ins[0]->id => ['resultat' => 'partiel', 'commentaire' => 'Encore quelques erreurs'],
            $this->ins[1]->id => ['resultat' => 'non'],
        ]])->assertRedirect(route('evaluations.index'))->assertSessionHas('succes', 'Mise en pratique enregistrée pour 1 agent(s).');
        $this->assertSame(1, EvaluationImpact::count());
        $this->actingAs($this->chef)->post(route('evaluations.froid.enregistrer', $a), ['lignes' => [$this->ins[0]->id => ['resultat' => 'oui']]]);
        $this->assertSame(1, EvaluationImpact::count(), 'Une seule évaluation par agent, mise à jour.');
        $this->assertSame('oui', EvaluationImpact::first()->resultat_atteint);
        $this->actingAs($this->chef)->get(route('evaluations.index', ['etat' => 'faites']))->assertSee('Tableur')->assertSee('1 évalué(s)');

        // L'autre service : son propre chef, ou la RH
        $this->actingAs($chef2)->get(route('evaluations.froid', $a))->assertOk()->assertSee('DIARRA Moussa')->assertDontSee('COULIBALY Awa');
        $this->actingAs($this->utilisateur('chef_service', $this->service2))->post(route('evaluations.froid.enregistrer', $a), ['lignes' => [$this->ins[1]->id => ['resultat' => 'non']]]);
        $this->actingAs($this->rh)->get(route('evaluations.froid', $a))->assertSee('DIARRA Moussa')->assertSee('COULIBALY Awa');
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertSee('2 évaluation(s) faite(s) sur 2 : Oui 1, En partie 0, Non 1');
        $this->actingAs($this->rh)->get(route('rapport-realisation.pdf', $a))->assertOk();
    }

    public function test_rappel_automatique_au_chef_du_service(): void
    {
        DB::table('sessions_formation')->where('id', $this->s->id)->update(['date_debut' => today()->subMonths(7), 'date_fin' => today()->subMonths(7)]);
        $this->artisan('inps:echeances')->assertSuccessful();
        $this->assertSame(1, $this->chef->notifications()->where('data->categorie', 'evaluation_a_realiser')->count());
        // Service 2 sans responsable de structure : la RH est prévenue ; le rappel n'est envoyé qu'une fois
        $this->assertSame(1, $this->rh->notifications()->where('data->categorie', 'evaluation_a_realiser')->count());
        $this->artisan('inps:echeances')->assertSuccessful();
        $this->assertSame(1, $this->chef->notifications()->where('data->categorie', 'evaluation_a_realiser')->count());
    }

    public function test_rapport_de_realisation(): void
    {
        $a = $this->s->action;
        $this->actingAs($this->chef)->get(route('rapport-realisation.edit', $a))->assertForbidden();
        $this->actingAs($this->rh)->get(route('rapport-realisation.edit', $a))->assertOk()->assertSee('2 ont suivi sur 3 inscrit(s)');
        $this->actingAs($this->rh)->put(route('rapport-realisation.update', $a), ['rapport_deroulement' => ''])->assertSessionHasErrors('rapport_deroulement');
        $this->actingAs($this->rh)->put(route('rapport-realisation.update', $a), ['rapport_deroulement' => 'Deux jours en salle', 'rapport_recommandations' => 'Un second groupe'])
            ->assertRedirect(route('formations.show', $a));
        $s = $this->s->fresh();
        $this->assertTrue($s->rapportRedige());
        $this->assertSame('Un second groupe', $s->rapport_recommandations);
        $this->actingAs($this->rh)->get(route('rapport-realisation.pdf', $a))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Pas de rapport pour une formation non terminée
        $autre = $this->nouvelleFormation($this->rh);
        $this->actingAs($this->rh)->get(route('rapport-realisation.edit', $autre->action))->assertNotFound();
    }

    public function test_attestation_d_intervention_du_formateur_externe(): void
    {
        $a = $this->s->action;
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertSee('Attestations d\'intervention des formateurs externes', false)->assertSee('TRAORE Ali');
        $this->actingAs($this->chef)->post(route('attestations-intervenants.emettre', [$a, $this->externe]))->assertForbidden();
        $this->actingAs($this->rh)->post(route('attestations-intervenants.emettre', [$a, $this->interne]))
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'formateur externe'));

        $this->actingAs($this->rh)->post(route('attestations-intervenants.emettre', [$a, $this->externe]))->assertRedirect()->assertSessionHas('succes');
        $att = AttestationIntervenant::firstOrFail();
        $this->assertMatchesRegularExpression('/^ATI-\d{4}-\d{4}$/', $att->reference);
        $this->actingAs($this->rh)->post(route('attestations-intervenants.emettre', [$a, $this->externe]))->assertRedirect();
        $this->assertSame(1, AttestationIntervenant::count(), 'Réémettre redonne la même attestation.');
        $this->actingAs($this->rh)->get(route('attestations-intervenants.pdf', $att))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertSee($att->reference);
    }
}
