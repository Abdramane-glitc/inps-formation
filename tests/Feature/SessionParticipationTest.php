<?php

namespace Tests\Feature;

use App\Enums\StatutAction;
use App\Enums\StatutInscription as I;
use App\Enums\StatutSession;
use App\Exceptions\RegleMetierException;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\Attestation;
use App\Models\Inscription;
use App\Models\SessionFormation;
use App\Models\User;
use App\Services\ActionService;
use App\Services\AttestationService;
use App\Services\EvaluationService;
use App\Services\InscriptionService;
use Tests\TestCase;

/**
 * Formation en trois étapes (version 1.5.0) : la formation, ses participants (« a suivi la formation »,
 * puis « Formation terminée »), les attestations.
 */
class SessionParticipationTest extends TestCase
{
    private User $drh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->drh = $this->utilisateur('responsable_drh');
    }

    private function agents(int $n): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = Agent::create(['matricule' => 'AG'.$i.uniqid(), 'nom' => 'AGENT'.$i, 'prenom' => 'P', 'service_id' => $this->service1->id])->id;
        }

        return $ids;
    }

    /** Formation du lundi 5 au mercredi 7 octobre 2026. */
    private function nouvelleSession(string $debut = '2026-10-05', string $fin = '2026-10-07'): SessionFormation
    {
        return $this->nouvelleFormation($this->drh, ['intitule' => 'Action test', 'date_debut' => $debut, 'date_fin' => $fin, 'places' => 1]);
    }

    /** Formation avec $n participants ; renvoie la session et les inscriptions (ordre d'id). */
    private function avecParticipants(int $n): array
    {
        $s = $this->nouvelleSession();
        app(InscriptionService::class)->inscrire($s, $this->agents($n), $this->drh);

        return [$s->fresh(), $s->participantsActifs()->orderBy('id')->get()];
    }

    public function test_creation_d_une_formation_en_un_seul_formulaire(): void
    {
        $base = ['intitule' => 'Interne', 'type' => 'interne', 'date_debut' => '2026-10-09', 'date_fin' => '2026-10-12', 'lieu' => 'Siège'];
        $this->actingAs($this->drh)->get(route('formations.create'))->assertOk()->assertSee('Plus de détails (facultatif)')
            ->assertDontSee('Nombre de places')->assertDontSee('Liste d\'attente')->assertDontSee('Coût');
        $this->actingAs($this->drh)->post('/formations', ['type' => 'externe'] + $base)->assertSessionHasErrors('organisme_id');
        $this->actingAs($this->drh)->post('/formations', ['date_fin' => '2026-10-01'] + $base)->assertSessionHasErrors('date_fin');
        $this->actingAs($this->drh)->post('/formations', array_diff_key($base, ['date_debut' => 1]))->assertSessionHasErrors('date_debut');
        $this->assertSame(0, ActionFormation::count());

        $this->actingAs($this->drh)->post('/formations', $base + ['statut' => 'realisee', 'reference' => 'X'])->assertRedirect(); // champs non autorisés ignorés
        $a = ActionFormation::firstOrFail();
        $this->assertMatchesRegularExpression('/^ACT-2026-\d{4}$/', $a->reference);
        $this->assertSame(StatutAction::Planifiee, $a->statut);
        $this->assertSame('Programmée', $a->statut->libelle());
        $this->assertSame(2026, $a->annee, 'Année déduite de la date de début.');
        $this->assertSame('presentiel', $a->modalite);
        $s = SessionFormation::firstOrFail();
        $this->assertSame($a->id, $s->action_id);
        $this->assertSame('Siège', $s->lieu);
        $this->actingAs($this->drh)->get(route('formations.show', $a))->assertOk()->assertSee('Étapes de la formation')->assertSee('Ajouter les participants')
            ->assertDontSee('Convoquer')->assertDontSee('Démarrer')->assertDontSee('Présences')->assertDontSee('Après la formation');
        $this->actingAs($this->drh)->get(route('sessions.show', $s))->assertRedirect(route('formations.show', $a)); // ancienne adresse

        // Une formation = une seule période : un second groupe = une autre formation
        $this->expectException(RegleMetierException::class);
        app(ActionService::class)->creerSession($a, ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02', 'places' => 5], $this->drh);
    }

    public function test_modification_de_la_formation_et_formation_similaire(): void
    {
        $s = $this->nouvelleSession();
        $a = $s->action;
        $this->actingAs($this->drh)->put(route('formations.update', $a), ['intitule' => 'Nouvel intitulé', 'type' => 'interne',
            'date_debut' => '2026-10-06', 'date_fin' => '2026-10-07', 'lieu' => 'Salle B'])->assertRedirect(route('formations.show', $a));
        $this->assertSame('Nouvel intitulé', $a->fresh()->intitule);
        $this->assertSame('2026-10-06', $s->fresh()->date_debut->toDateString());
        $this->assertSame('Salle B', $s->fresh()->lieu);
        $this->assertSame('presentiel', $a->fresh()->modalite, 'Modalité conservée si non envoyée.');
        $this->assertSame(1, $a->sessions()->count());
        $this->actingAs($this->drh)->get(route('formations.create', ['modele' => $a->id]))->assertOk()->assertSee('Nouvel intitulé')->assertSee('Salle B');
    }

    public function test_ajout_des_participants_sans_limite_de_places_via_http(): void
    {
        $s = $this->nouvelleSession();
        $a = $s->action;
        [$a1, $a2] = $this->agents(2);
        $this->actingAs($this->drh)->post(route('formations.participants', $a), [])->assertSessionHas('erreur');
        $this->actingAs($this->drh)->post(route('formations.participants', $a), ['agents' => [$a1, $a2]])->assertSessionHas('succes');
        $this->assertSame(2, $s->placesOccupees());
        $this->assertSame(2, $s->fresh()->places, 'Les places suivent le nombre de participants.');
        // Tout un service : les agents déjà inscrits sont signalés, pas dupliqués
        $this->agents(2);
        $this->actingAs($this->drh)->post(route('formations.participants', $a), ['service_id' => $this->service1->id])
            ->assertSessionHas('succes', '2 participant(s) ajouté(s).')->assertSessionHas('avertissements', fn ($av) => count($av) === 2 && str_contains($av[0], 'déjà inscrit'));
        $this->assertSame(4, $s->placesOccupees());
        $this->assertSame(0, Inscription::where('statut', 'liste_attente')->count());
        $this->actingAs($this->drh)->get(route('formations.show', $a))->assertSee('Participants (4)')->assertSee('Après la formation : cocher qui', false);
        $this->actingAs($this->drh)->get(route('sessions.emargement', $s))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_agent_inactif_doublon_et_chevauchement_refuses(): void
    {
        $s1 = $this->nouvelleSession();
        $s2 = $this->nouvelleSession('2026-10-06', '2026-10-08');
        [$a1, $a2, $a3] = $this->agents(3);
        Agent::whereKey($a3)->update(['actif' => false]);
        $svc = app(InscriptionService::class);
        $r = $svc->inscrire($s1, [$a1, $a2, $a3], $this->drh);
        $this->assertCount(2, $r['inscrits']);
        $this->assertStringContainsString('agent inactif', $r['refus'][0]);
        $this->assertCount(1, $svc->inscrire($s1, [$a1], $this->drh)['refus'], 'Doublon refusé.');
        $r = $svc->inscrire($s2, [$a1], $this->drh);
        $this->assertStringContainsString('chevauchent', $r['refus'][0]);
    }

    public function test_retrait_d_un_participant_conserve_l_historique(): void
    {
        [$s, $ins] = $this->avecParticipants(2);
        $chef = $this->utilisateur('chef_service');
        $this->actingAs($chef)->post(route('formations.participants.retirer', $ins[0]))->assertForbidden();
        $this->actingAs($this->drh)->post(route('formations.participants.retirer', $ins[0]))->assertSessionHas('succes');
        $this->assertSame(I::Desiste, $ins[0]->fresh()->statut);
        $this->assertDatabaseHas('inscription_evenements', ['inscription_id' => $ins[0]->id, 'evenement' => 'retrait']);
        $this->assertSame(1, $s->placesOccupees());
        $this->actingAs($this->drh)->post(route('formations.participants.retirer', $ins[0]))->assertSessionHas('erreur');
        // Réinscription possible tant que la formation est ouverte
        app(InscriptionService::class)->inscrire($s, [$ins[0]->agent_id], $this->drh);
        $this->assertSame(I::Inscrit, $ins[0]->fresh()->statut);
    }

    public function test_formation_terminee_avec_les_participants_coches_via_http(): void
    {
        [$s, $ins] = $this->avecParticipants(3);
        $a = $s->action;
        $chef = $this->utilisateur('chef_service');
        $this->actingAs($chef)->post(route('formations.terminer', $a), ['suivi' => [$ins[0]->id]])->assertForbidden();

        // Avant « Formation terminée » : pas d'attestation
        $this->assertNotEmpty(app(AttestationService::class)->conditionsNonRemplies($ins[0]));
        $this->actingAs($this->drh)->post(route('attestations.store', $ins[0]))->assertSessionHas('erreur');

        // Seuls les deux premiers ont suivi ; un identifiant étranger est ignoré
        $this->actingAs($this->drh)->post(route('formations.terminer', $a), ['suivi' => [$ins[0]->id, $ins[1]->id, 999999]])->assertSessionHas('succes');
        $s->refresh();
        $this->assertSame(StatutSession::Terminee, $s->statut);
        $this->assertSame(StatutAction::Realisee, $a->fresh()->statut, 'La formation suit son déroulement.');
        $this->assertNotNull($s->presences_validees_le);
        $this->assertSame(['achevee', 'achevee', 'non_achevee'], $s->inscriptions()->orderBy('id')->pluck('resultat')->all());
        $this->assertSame(100.0, (float) $ins[0]->fresh()->taux_presence);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.terminee']);

        // La liste ne change plus ; la page propose les attestations
        $this->expectExceptionObject(new RegleMetierException('La formation est « Terminée » : la liste des participants ne peut plus changer.'));
        $this->actingAs($this->drh)->get(route('formations.show', $a))->assertSee('Émettre les attestations')->assertDontSee('Ajouter des participants');
        app(InscriptionService::class)->inscrire($s, $this->agents(1), $this->drh);
    }

    public function test_attestations_pour_ceux_qui_ont_suivi_et_correction(): void
    {
        [$s, $ins] = $this->avecParticipants(3);
        $svc = app(ActionService::class);
        $svc->terminer($s, [$ins[0]->id, $ins[1]->id], $this->drh);

        // Émission groupée : seuls les participants cochés
        $this->actingAs($this->drh)->post(route('attestations.session', $s))->assertSessionHas('succes', '2 attestation(s) émise(s).');
        $this->assertSame(2, Attestation::count());
        $this->assertSame("L'agent n'a pas suivi la formation.", app(AttestationService::class)->conditionsNonRemplies($ins[2]->fresh())[0] ?? null);
        $this->actingAs($this->drh)->post(route('attestations.store', $ins[2]))->assertSessionHas('erreur');
        $att = Attestation::where('inscription_id', $ins[0]->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^ATT-\d{4}-\d{4}$/', $att->reference);
        $this->assertSame(32, strlen($att->code_verification));
        $this->actingAs($this->drh)->get(route('attestations.pdf', $att))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty(app(AttestationService::class)->conditionsNonRemplies($ins[0]->fresh()), 'Pas de doublon d\'attestation.');
        // 1.9.0 : attestations émises, il reste les évaluations à chaud et le rapport de réalisation
        $this->actingAs($this->drh)->get(route('formations.show', $s->action))->assertSee('indiquer qui a compris, puis rédiger le rapport de réalisation')->assertSee('Après la formation');
        app(EvaluationService::class)->enregistrerRapport($s->fresh(), ['rapport_deroulement' => 'Bien déroulée'], $this->drh);
        $this->actingAs($this->drh)->get(route('formations.show', $s->action))->assertSee('Formation terminée')->assertSee('Toutes les étapes sont faites');

        // Correction : on ajoute le troisième ; on ne peut pas décocher un participant déjà attesté
        $svc->terminer($s->fresh(), [$ins[0]->id, $ins[1]->id, $ins[2]->id], $this->drh);
        $this->assertSame('achevee', $ins[2]->fresh()->resultat);
        $this->assertDatabaseHas('inscription_evenements', ['inscription_id' => $ins[2]->id, 'evenement' => 'correction_suivi']);
        $this->actingAs($this->drh)->post(route('formations.terminer', $s->action), ['suivi' => [$ins[1]->id, $ins[2]->id]])->assertSessionHas('erreur');
        $this->assertSame('achevee', $ins[0]->fresh()->resultat);

        // Après annulation de l'attestation, la correction est possible
        $this->actingAs($this->drh)->post(route('attestations.annuler', $att), ['motif' => 'Erreur'])->assertSessionHas('succes');
        $this->actingAs($this->drh)->get(route('attestations.pdf', $att))->assertStatus(410);
        $this->actingAs($this->drh)->post(route('formations.terminer', $s->action), ['suivi' => [$ins[1]->id, $ins[2]->id]])->assertSessionHas('succes', 'Suivi corrigé.');
        $this->assertSame('non_achevee', $ins[0]->fresh()->resultat);
    }

    public function test_terminer_exige_des_participants_et_une_formation_non_annulee(): void
    {
        $s = $this->nouvelleSession();
        $this->actingAs($this->drh)->post(route('formations.terminer', $s->action), [])->assertSessionHas('erreur');
        app(InscriptionService::class)->inscrire($s, $this->agents(1), $this->drh);
        $this->actingAs($this->drh)->post(route('sessions.annuler', $s), [])->assertSessionHasErrors('motif');
        $this->actingAs($this->drh)->post(route('sessions.annuler', $s), ['motif' => 'Formateur indisponible'])->assertSessionHas('succes');
        $this->assertSame(StatutSession::Annulee, $s->fresh()->statut);
        $this->assertSame(StatutAction::Annulee, $s->action->fresh()->statut, 'La formation suit sa session.');
        $this->assertSame(I::Annule, Inscription::firstOrFail()->statut);
        $this->actingAs($this->drh)->get(route('formations.show', $s->action))->assertSee('Formation annulée')->assertSee('Formateur indisponible');
        $this->expectException(RegleMetierException::class);
        app(ActionService::class)->terminer($s->fresh(), [], $this->drh);
    }

    public function test_routes_des_etapes_supprimees_n_existent_plus(): void
    {
        foreach (['presences.update', 'presences.corriger', 'evaluations.acquis', 'evaluations.satisfaction', 'evaluations.impact', 'inscriptions.store',
            'inscriptions.convoquer', 'sessions.reporter', 'sessions.statut', 'sessions.convocations', 'sessions.bilan'] as $nom) {
            $this->assertFalse(\Route::has($nom), "La route $nom ne doit plus exister.");
        }
    }

    public function test_chef_de_service_ne_voit_que_les_formations_et_participants_de_son_service(): void
    {
        $agent = fn (string $m, $service) => Agent::create(['matricule' => $m, 'nom' => 'NOM'.$m, 'prenom' => 'P', 'service_id' => $service->id])->id;
        $s1a = $agent('S1A', $this->service1);
        $s2a = $agent('S2A', $this->service2);
        $mixte = $this->nouvelleFormation($this->drh, ['intitule' => 'Formation mixte']);
        app(InscriptionService::class)->inscrire($mixte, [$s1a, $s2a], $this->drh);
        $autre = $this->nouvelleFormation($this->drh, ['intitule' => 'Formation du service 2', 'date_debut' => '2026-11-09', 'date_fin' => '2026-11-09']);
        app(InscriptionService::class)->inscrire($autre, [$agent('S2B', $this->service2)], $this->drh);
        $doc = $autre->action->documents()->make()->forceFill(['titre' => 'Programme', 'chemin' => 'documents/x.pdf', 'nom_original' => 'x.pdf', 'mime' => 'application/pdf', 'taille' => 10, 'sha256' => str_repeat('a', 64), 'depose_par' => $this->drh->id]);
        $doc->save();
        $chef1 = $this->utilisateur('chef_service', $this->service1);

        // Liste : seulement les formations qui concernent son service
        $this->actingAs($chef1)->get(route('formations.index'))->assertOk()->assertSee('Formation mixte')->assertDontSee('Formation du service 2')
            ->assertSee('Agents de votre service');
        // Fiche : seulement les participants de son service
        $this->actingAs($chef1)->get(route('formations.show', $mixte->action))->assertOk()->assertSee('Participants de votre service (1)')
            ->assertSee('NOMS1A')->assertDontSee('NOMS2A');
        $this->actingAs($chef1)->get(route('formations.show', $autre->action))->assertForbidden();
        $this->actingAs($chef1)->get(route('sessions.emargement', $autre))->assertForbidden();
        $this->actingAs($chef1)->get(route('documents.telecharger', $doc))->assertForbidden();

        // La RH voit tout, regroupé par service ; l'ajout d'autres participants est replié
        $this->actingAs($this->drh)->get(route('formations.show', $mixte->action))->assertOk()->assertSee('Participants (2) — 2 services')
            ->assertSeeInOrder(['Service test 1', 'NOMS1A', 'Service test 2', 'NOMS2A'])->assertSee('Ajouter d&#039;autres participants (facultatif)', false);
    }
}
