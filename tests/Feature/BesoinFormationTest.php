<?php

namespace Tests\Feature;

use App\Enums\StatutBesoin;
use App\Enums\StatutSession;
use App\Exceptions\RegleMetierException;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\AnneeReference;
use App\Models\BesoinFormation;
use App\Models\Decision;
use App\Models\Organisme;
use App\Services\ActionService;
use App\Services\BesoinService;
use App\Services\IndicateurService;
use App\Services\InscriptionService;
use App\Services\RapportService;
use Tests\TestCase;

/**
 * Demandes de formation (version 1.5.0) : le responsable de structure coche les agents à former ;
 * la RH accepte en fixant les dates, ce qui crée la formation et y inscrit les agents.
 */
class BesoinFormationTest extends TestCase
{
    private function agents(int $n, ?int $serviceId = null): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = Agent::create(['matricule' => 'BA'.$i.uniqid(), 'nom' => 'AGENT'.$i, 'prenom' => 'P', 'service_id' => $serviceId ?? $this->service1->id])->id;
        }

        return $ids;
    }

    private function demande(string $intitule = 'Demande', ?array $agents = null): BesoinFormation
    {
        return app(BesoinService::class)->creer(['intitule' => $intitule, 'contexte' => 'Savoir faire X'], $this->utilisateur('chef_service'), $agents ?? $this->agents(2));
    }

    public function test_cycle_de_vie_d_une_demande_simplifiee_via_http(): void
    {
        $this->travelTo('2026-10-22 09:00');
        $chef = $this->utilisateur('chef_service');
        $drh = $this->utilisateur('responsable_drh');
        [$a1, $a2, $a3] = $this->agents(3);
        [$autre] = $this->agents(1, $this->service2->id);

        // Formulaire minimal : on coche les agents, il n'y a plus de nombre de participants à saisir
        $this->actingAs($chef)->get(route('besoins.create'))->assertOk()->assertSee('Quel problème constatez-vous dans le service ?')->assertSee('Après la formation, que devront savoir faire les agents ?')->assertSee('Agents à former')
            ->assertDontSee('Nombre de participants')->assertDontSee('Coût')->assertDontSee('Priorité')->assertDontSee('Année de référence');
        $this->actingAs($chef)->post('/besoins', $this->analyse() + ['intitule' => 'Sans raison', 'agents' => [$a1]])->assertSessionHasErrors('contexte');
        $this->actingAs($chef)->post('/besoins', $this->analyse() + ['intitule' => 'Sans agent', 'contexte' => 'X'])->assertSessionHasErrors('agents');
        // Un agent d'un autre service n'est pas retenu : sans agent autorisé, la demande est refusée
        $this->actingAs($chef)->post('/besoins', $this->analyse() + ['intitule' => 'Autre service', 'contexte' => 'X', 'agents' => [$autre]])->assertSessionHasErrors('agents');
        $this->assertSame(0, BesoinFormation::count());

        // Envoi direct à la RH (champs non autorisés ignorés) ; le nombre suit les agents cochés
        $this->actingAs($chef)->post('/besoins', $this->analyse() + ['intitule' => 'Excel avancé', 'contexte' => 'Erreurs de saisie', 'agents' => [$a1, $a2, $autre], 'nb_participants' => 10,
            'periode_souhaitee' => 'Avant juin', 'statut' => 'acceptee', 'auteur_id' => $drh->id])->assertRedirect();
        $b = BesoinFormation::firstOrFail();
        $this->assertSame(StatutBesoin::Soumise, $b->statut);
        $this->assertSame('Envoyée', $b->statut->libelle());
        $this->assertSame($chef->id, $b->auteur_id);
        $this->assertSame($this->service1->id, $b->service_id, 'Service du responsable de structure.');
        $this->assertSame(2026, $b->annee, 'Année déterminée automatiquement.');
        $this->assertEqualsCanonicalizing([$a1, $a2], $b->agents()->pluck('agents.id')->all(), 'Agent hors service écarté.');
        $this->assertSame(2, $b->nb_participants, 'Le nombre est celui des agents cochés, pas la valeur envoyée.');
        $this->assertMatchesRegularExpression('/^BES-2026-\d{4}$/', $b->reference);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $drh->id]);
        $this->actingAs($chef)->put(route('besoins.update', $b), $this->analyse() + ['intitule' => 'Modifié', 'contexte' => 'X', 'agents' => [$a1]])->assertForbidden();

        // La RH décide depuis l'encadré « Prochaine étape » : il n'y a plus d'acceptation sans dates
        $this->actingAs($drh)->get(route('besoins.show', $b))->assertOk()->assertSee('Décider de la demande')->assertSee('Accepter et programmer');
        $this->actingAs($drh)->post(route('besoins.transition', $b), ['action' => 'acceptation'])->assertSessionHasErrors('action');
        $this->actingAs($drh)->post(route('besoins.transition', $b), ['action' => 'demande_complement'])->assertSessionHas('erreur'); // motif obligatoire
        $this->actingAs($drh)->post(route('besoins.transition', $b), ['action' => 'demande_complement', 'commentaire' => 'Ajouter un agent'])->assertSessionHas('succes');
        $this->assertSame(StatutBesoin::ACompleter, $b->fresh()->statut);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $chef->id]);
        $this->actingAs($chef)->get(route('besoins.show', $b))->assertSee('Ajouter un agent')->assertSee('Compléter la demande');

        // Compléter = renvoyer
        $this->actingAs($chef)->put(route('besoins.update', $b), $this->analyse() + ['intitule' => 'Excel avancé (complété)', 'contexte' => 'Erreurs de saisie', 'agents' => [$a1, $a2, $a3]])->assertRedirect();
        $this->assertSame(StatutBesoin::Soumise, $b->fresh()->statut);
        $this->assertSame(3, $b->fresh()->nb_participants);
        $this->actingAs($drh)->post(route('besoins.transition', $b), ['action' => 'rejet', 'commentaire' => 'Hors priorités'])->assertSessionHas('succes');
        $this->assertSame('Refusée', $b->fresh()->statut->libelle());
        $this->actingAs($chef)->get(route('besoins.show', $b))->assertSee('Motif : Hors priorités');

        $actions = Decision::where('decidable_id', $b->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame(['soumission', 'demande_complement', 'soumission', 'rejet'], $actions);
        $this->actingAs($drh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'])->assertSessionHas('erreur');
        $this->assertSame(0, ActionFormation::count());
    }

    public function test_l_auteur_supprime_sa_demande_tant_que_la_rh_n_a_pas_decide(): void
    {
        $chef = $this->utilisateur('chef_service');
        $b = app(BesoinService::class)->creer(['intitule' => 'X', 'contexte' => 'Y'], $chef, $this->agents(1));
        app(BesoinService::class)->transition($b, 'rejet', $this->utilisateur('responsable_drh'), 'Non');
        $this->actingAs($chef)->delete(route('besoins.destroy', $b))->assertForbidden(); // décision prise
        $envoyee = app(BesoinService::class)->creer(['intitule' => 'Envoyée', 'contexte' => 'Y'], $chef, $this->agents(1));
        $this->actingAs($chef)->delete(route('besoins.destroy', $envoyee))->assertRedirect();
        $this->assertSoftDeleted($envoyee);
        // Brouillon enregistré avant la version 1.4.0
        $ancien = app(BesoinService::class)->creer(['intitule' => 'Ancien', 'contexte' => 'Y'], $chef, $this->agents(1));
        $ancien->forceFill(['statut' => StatutBesoin::Brouillon])->save();
        $this->actingAs($chef)->get(route('besoins.show', $ancien))->assertSee('Envoyer la demande');
        $this->actingAs($chef)->delete(route('besoins.destroy', $ancien))->assertRedirect();
        $this->assertSoftDeleted($ancien);
    }

    public function test_annee_de_la_demande_determinee_automatiquement(): void
    {
        $this->travelTo('2026-10-22 09:00');
        $chef = $this->utilisateur('chef_service');
        AnneeReference::where('annee', 2026)->update(['statut' => 'cloturee']);
        AnneeReference::create(['annee' => 2027, 'statut' => 'ouverte']);
        $this->assertSame(2027, app(BesoinService::class)->creer(['intitule' => 'X', 'contexte' => 'Y'], $chef)->annee, 'Année en cours clôturée : année suivante ouverte.');
        AnneeReference::where('annee', 2027)->update(['statut' => 'cloturee']);
        $this->expectException(RegleMetierException::class);
        app(BesoinService::class)->creer(['intitule' => 'X', 'contexte' => 'Y'], $chef);
    }

    public function test_annee_creee_si_aucune_annee_n_existe(): void
    {
        $this->travelTo('2027-01-05 09:00');
        $b = app(BesoinService::class)->creer(['intitule' => 'X', 'contexte' => 'Y'], $this->utilisateur('chef_service'));
        $this->assertSame(2027, $b->annee);
        $this->assertTrue(AnneeReference::where('annee', 2027)->firstOrFail()->estOuverte());
    }

    public function test_accepter_programme_la_formation_et_inscrit_les_agents_via_http(): void
    {
        $agents = $this->agents(3);
        $b = $this->demande('Gestion du temps', $agents);
        $chef = $b->auteur;
        $drh = $this->utilisateur('responsable_drh');
        $org = Organisme::create(['raison_sociale' => 'Cabinet test', 'actif' => true]);

        // Le responsable de structure attend ; il n'existe plus de bouton « Organiser la formation »
        $this->actingAs($chef)->get(route('besoins.show', $b))->assertOk()->assertSee('La RH étudie la demande')->assertDontSee('Accepter et programmer');
        $this->actingAs($drh)->get(route('besoins.show', $b))->assertOk()->assertDontSee('Organiser la formation')->assertSee('Cabinet test');

        // Le responsable de structure ne peut pas accepter ; la RH doit fixer les dates
        $this->actingAs($chef)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-04'])->assertForbidden();
        $this->actingAs($drh)->post(route('besoins.accepter', $b), ['lieu' => 'Siège'])->assertSessionHasErrors(['date_debut', 'date_fin']);
        $this->actingAs($drh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-04', 'date_fin' => '2026-11-02'])->assertSessionHasErrors('date_fin');
        $this->assertSame(0, ActionFormation::count());

        // Un clic : demande acceptée, formation créée, agents inscrits
        $rep = $this->actingAs($drh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-04', 'lieu' => 'Salle du siège',
            'organisme_id' => $org->id, 'commentaire' => 'Bonne demande']);
        $a = ActionFormation::firstOrFail();
        $rep->assertRedirect(route('formations.show', $a));
        $this->assertSame($b->id, $a->besoin_id);
        $this->assertSame('Gestion du temps', $a->intitule);
        $this->assertSame('Savoir faire X', $a->description);
        $this->assertSame('externe', $a->type);
        $this->assertSame($org->id, $a->organisme_id);
        $s = $a->session()->firstOrFail();
        $this->assertSame('2026-11-02', $s->date_debut->format('Y-m-d'));
        $this->assertSame('Salle du siège', $s->lieu);
        $this->assertSame(StatutSession::Planifiee, $s->statut);
        $this->assertEqualsCanonicalizing($agents, $s->participantsActifs()->pluck('agent_id')->all());
        $this->assertSame(StatutBesoin::Organisee, $b->fresh()->statut);
        $this->assertSame('Formation programmée', $b->fresh()->statut->libelle());
        $this->assertSame(['soumission', 'acceptation', 'organisation'], Decision::where('decidable_id', $b->id)->orderBy('id')->pluck('action')->all());
        $this->assertSame('Bonne demande', Decision::where('decidable_id', $b->id)->where('action', 'acceptation')->value('commentaire'));
        $this->assertSame(1, \DB::table('notifications')->where('notifiable_id', $chef->id)->count(), 'Une seule notification au responsable de structure.');

        // Une seule formation par demande
        $this->actingAs($drh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-12-01', 'date_fin' => '2026-12-01'])->assertSessionHas('erreur');
        $this->assertSame(1, ActionFormation::count());

        // La fiche de la formation propose l'étape suivante ; le responsable de structure voit les dates
        $this->actingAs($drh)->get(route('formations.show', $a))->assertOk()->assertSee('Participants (3)')->assertSee('Formation terminée')->assertSee($b->reference);
        $this->actingAs($chef)->get(route('besoins.show', $b))->assertOk()->assertSee('formation programmée')->assertSee($a->reference)->assertSee('02/11/2026');
    }

    public function test_demande_acceptee_avant_la_mise_a_jour_se_programme_aussi(): void
    {
        $b = $this->demande('Ancienne', $this->agents(1));
        $drh = $this->utilisateur('responsable_drh');
        $b->forceFill(['statut' => StatutBesoin::Acceptee])->save();
        $this->actingAs($drh)->get(route('besoins.show', $b))->assertSee('Programmer la formation');
        $this->actingAs($drh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'])->assertRedirect();
        $this->assertSame(StatutBesoin::Organisee, $b->fresh()->statut);
        $this->assertSame('interne', ActionFormation::firstOrFail()->type);
        $this->assertSame(1, ActionFormation::firstOrFail()->session->participantsActifs()->count());
    }

    public function test_seule_une_demande_acceptee_peut_recevoir_une_formation(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $b = $this->demande('Envoyée seulement');
        // L'organisation ne passe pas par la route des décisions ni par le formulaire de formation
        $this->actingAs($drh)->post(route('besoins.transition', $b), ['action' => 'organisation'])->assertSessionHasErrors('action');
        $this->actingAs($drh)->post(route('formations.store'), ['intitule' => 'X', 'type' => 'interne', 'date_debut' => '2026-11-02', 'date_fin' => '2026-11-02', 'besoin_id' => $b->id])->assertRedirect();
        $this->assertNull(ActionFormation::firstOrFail()->besoin_id, 'besoin_id ignoré par le formulaire.');
        $this->assertSame(StatutBesoin::Soumise, $b->fresh()->statut);

        $this->expectException(RegleMetierException::class);
        app(ActionService::class)->creerFormation(['intitule' => 'X', 'date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'], $drh, $b->fresh());
    }

    public function test_formation_sans_demande_prealable(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->post(route('formations.store'), ['intitule' => 'Atelier sécurité', 'type' => 'interne', 'date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'])->assertRedirect();
        $a = ActionFormation::firstOrFail();
        $this->assertNull($a->besoin_id);
        $this->assertSame('presentiel', $a->modalite, 'Modalité par défaut.');
    }

    public function test_demandes_a_organiser_et_rapport_de_suivi(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $b1 = $this->demande('Programmée');
        $b2 = $this->demande('Acceptée sans dates');
        $this->demande('En attente');
        app(ActionService::class)->accepterEtProgrammer($b1, ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-04'], $drh);
        app(BesoinService::class)->transition($b2, 'acceptation', $drh); // donnée antérieure à 1.5.0

        $k = app(IndicateurService::class)->tableauDeBord(['annee' => 2026]);
        $this->assertSame(1, $k['demandes_a_organiser']);
        $this->assertSame(1, $k['demandes_en_attente']);

        $r = app(RapportService::class)->generer('suivi_demandes', ['annee' => 2026]);
        $this->assertCount(3, $r['lignes']);
        $parIntitule = collect($r['lignes'])->keyBy(1);
        $this->assertSame('Acceptée', $parIntitule['Programmée'][3]);
        $this->assertSame('Programmée', $parIntitule['Programmée'][6]);
        $this->assertSame('02/11/2026 – 04/11/2026', $parIntitule['Programmée'][5]);
        $this->assertSame('—', $parIntitule['En attente'][4]);
        $this->assertStringContainsString('2 demande(s) acceptée(s), dont 1 avec une formation programmée', $r['note']);
    }

    public function test_un_agent_de_la_demande_non_inscrit_est_signale_et_jamais_ignore(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        [$a1, $a2, $occupe] = $this->agents(3);
        // L'un des trois est déjà inscrit à une autre formation aux mêmes dates
        $autre = $this->nouvelleFormation($drh, ['intitule' => 'Autre formation', 'date_debut' => '2026-11-03', 'date_fin' => '2026-11-03']);
        app(InscriptionService::class)->inscrire($autre, [$occupe], $drh);
        $b = $this->demande('Trois agents', [$a1, $a2, $occupe]);
        $this->assertSame(3, $b->nb_participants);

        $this->actingAs($drh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-04'])
            ->assertSessionHas('succes', fn ($m) => str_contains($m, '1 agent(s) de la demande n\'ont pas pu être inscrits'))
            ->assertSessionHas('avertissements', fn ($av) => count($av) === 1 && str_contains($av[0], 'chevauchent'));
        $a = ActionFormation::where('besoin_id', $b->id)->firstOrFail();
        $this->assertSame(2, $a->session->participantsActifs()->count());
        // L'information reste sur la fiche tant que l'agent n'est pas inscrit
        $this->actingAs($drh)->get(route('formations.show', $a))->assertSee('1 agent(s) de la demande '.$b->reference.' ne sont pas inscrits', false)
            ->assertSee('Autre formation');
    }

    public function test_le_statut_affiche_d_une_demande_suit_sa_formation(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $b = $this->demande('Suivie jusqu\'au bout', $this->agents(1));
        $a = app(ActionService::class)->accepterEtProgrammer($b, ['date_debut' => '2026-10-05', 'date_fin' => '2026-10-06'], $drh);
        $this->travelTo('2026-10-22 09:00');
        $chef = $b->auteur;
        $this->actingAs($chef)->get(route('besoins.index'))->assertSee('Formation programmée');
        // Dates passées sans « Formation terminée » : signalé côté formations
        $this->actingAs($drh)->get(route('formations.index'))->assertSee('À terminer');
        $this->actingAs($drh)->get(route('formations.index', ['statut' => 'a_terminer']))->assertSee($a->reference);

        app(ActionService::class)->terminer($a->session, $a->session->participantsActifs()->pluck('id')->all(), $drh);
        $this->assertSame(StatutBesoin::Organisee, $b->fresh()->statut, 'Le statut enregistré ne change pas.');
        $this->assertSame(['Formation terminée', 'vert'], $b->fresh()->statutAffiche());
        $this->actingAs($chef)->get(route('besoins.index'))->assertSee('Formation terminée')->assertDontSee('Formation programmée</span>', false);
        $this->actingAs($chef)->get(route('besoins.show', $b))->assertSee('Demande satisfaite — formation terminée');
        $this->actingAs($chef)->get(route('besoins.index', ['statut' => 'formation_terminee']))->assertSee($b->reference);
        $this->actingAs($chef)->get(route('besoins.index', ['statut' => 'organisee']))->assertDontSee(route('besoins.show', $b), false);
        $this->actingAs($drh)->get(route('formations.index'))->assertDontSee('À terminer</span>', false);
    }

    /** 2.1.0 : analyse du besoin — diagnostic savoir-faire / moyens / temps, gravité, mesure, solutions essayées. */
    public function test_analyse_du_besoin(): void
    {
        $chef = $this->utilisateur('chef_service');
        [$a1] = $this->agents(1);
        $base = ['intitule' => 'Classement', 'contexte' => 'Dossiers égarés', 'agents' => [$a1]];
        $this->actingAs($chef)->get(route('besoins.create'))->assertOk()->assertSee('Aujourd&#039;hui, les agents savent-ils faire correctement ce travail ?', false)
            ->assertSee('Quelle est la gravité du problème ?')->assertSee('Comment mesurer l\'amélioration ?', false);
        // Toutes les questions sont obligatoires ; « Rien pour l'instant » ne se combine pas
        $this->actingAs($chef)->post('/besoins', $base)->assertSessionHasErrors(['diag_savoir', 'diag_moyens', 'diag_temps', 'gravite', 'situation_actuelle',
            'objectif_vise', 'solutions_essayees', 'justification_formation', 'resultat_attendu']);
        $this->actingAs($chef)->post('/besoins', ['solutions_essayees' => ['aucune', 'collegue']] + $this->analyse() + $base)->assertSessionHasErrors('solutions_essayees');
        $this->actingAs($chef)->post('/besoins', ['diag_savoir' => 'peut-etre'] + $this->analyse() + $base)->assertSessionHasErrors('diag_savoir');

        // Les agents savent déjà faire : la demande part, la RH est prévenue
        $this->actingAs($chef)->post('/besoins', ['diag_savoir' => 'oui', 'diag_moyens' => 'non', 'gravite' => 'risque', 'situation_actuelle' => '30 minutes pour retrouver un dossier',
            'objectif_vise' => 'Moins de 5 minutes', 'solutions_essayees' => ['collegue', 'procedure']] + $this->analyse() + $base)->assertSessionHasNoErrors();
        $b = BesoinFormation::latest('id')->firstOrFail();
        $this->assertTrue($b->causesHorsCompetences());
        $this->assertSame('rouge', $b->conclusionDiagnostic()[0]);
        $rh = $this->utilisateur('responsable_drh');
        $this->actingAs($rh)->get(route('besoins.show', $b))->assertOk()->assertSee('Aide à la décision')
            ->assertSee('Les agents savent déjà faire : une formation ne réglera probablement pas ce problème', false)
            ->assertSee('Risque pour les assurés ou pour l&#039;Institut', false)->assertSee('Moins de 5 minutes')->assertSee('Accompagnement par un collègue, Note ou procédure écrite');
        // Le responsable de structure voit son analyse, pas l'aide à la décision réservée à la RH
        $this->actingAs($chef)->get(route('besoins.show', $b))->assertOk()->assertSee('Moins de 5 minutes')->assertDontSee('Aide à la décision');

        // Conclusions : savoir-faire manquant avec ou sans moyens / temps
        $b->update(['diag_savoir' => 'partiel', 'diag_moyens' => 'oui', 'diag_temps' => 'non']);
        $this->assertSame(['orange', 'Il manque du savoir-faire, mais aussi du temps : la formation seule ne suffira pas.'], $b->fresh()->conclusionDiagnostic());
        $b->update(['diag_savoir' => 'non', 'diag_moyens' => 'oui', 'diag_temps' => 'oui']);
        $this->assertSame('vert', $b->fresh()->conclusionDiagnostic()[0]);
        $this->assertFalse($b->fresh()->causesHorsCompetences());

        // Demandes antérieures : causes à cocher (1.8.0) et texte libre (avant 1.8.0) restent lisibles
        $v18 = app(BesoinService::class)->creer(['intitule' => 'Ancienne 1.8', 'contexte' => 'Texte', 'causes' => ['moyens'], 'justification_formation' => 'J', 'resultat_attendu' => 'R'], $chef, [$a1]);
        $this->assertTrue($v18->causesHorsCompetences());
        $this->actingAs($rh)->get(route('besoins.show', $v18))->assertOk()->assertSee('Origine du problème')->assertSee('Matériel, logiciel ou équipement insuffisant ou en panne');
        $ancienne = app(BesoinService::class)->creer(['intitule' => 'Ancienne', 'contexte' => 'Texte libre'], $chef, [$a1]);
        $this->actingAs($rh)->get(route('besoins.show', $ancienne))->assertOk()->assertSee('Pourquoi cette formation ?')->assertSee('Texte libre')->assertDontSee('Origine du problème');
    }
}
