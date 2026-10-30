<?php

namespace Tests\Feature;

use App\Enums\StatutBesoin;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\BesoinFormation;
use App\Models\Decision;
use App\Models\PlanAnnuel;
use App\Models\PlanLigne;
use App\Models\User;
use App\Services\BesoinService;
use Tests\TestCase;

/** Plan annuel simple (version 1.7.0). */
class PlanAnnuelTest extends TestCase
{
    private User $rh;

    private User $chef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-23 09:00');
        $this->rh = $this->utilisateur('responsable_drh');
        $this->chef = $this->utilisateur('chef_service', $this->service1);
    }

    private function demande(string $intitule = 'Rédaction', int $nb = 2): BesoinFormation
    {
        $ids = [];
        for ($i = 1; $i <= $nb; $i++) {
            $ids[] = Agent::create(['matricule' => 'PL'.$i.uniqid(), 'nom' => 'PLAN'.$i, 'prenom' => 'P', 'service_id' => $this->service1->id])->id;
        }

        return app(BesoinService::class)->creer(['intitule' => $intitule, 'contexte' => 'Courriers'], $this->chef, $ids);
    }

    public function test_la_rh_prevoit_puis_programme_une_formation_du_plan(): void
    {
        $this->actingAs($this->rh)->get(route('plan.index'))->assertOk()->assertSee('Plan annuel de formation 2026')->assertSee('Ajouter une formation au plan 2026');
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => '', 'periode' => 'T1'])->assertSessionHasErrors('intitule');
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'Sécurité incendie', 'periode' => 'X'])->assertSessionHasErrors('periode');
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'Sécurité incendie', 'periode' => 'M11',
            'service_id' => $this->service2->id, 'nb_agents' => 12, 'type' => 'interne'])->assertRedirect(route('plan.index', ['annee' => 2026]));
        $l = PlanLigne::firstOrFail();
        $this->assertSame('T4', $l->trimestre());
        $this->assertSame('prevue', $l->etat());
        $this->actingAs($this->rh)->get(route('plan.index'))->assertSee('4e trimestre (1)')->assertSee('Novembre')->assertSee('Prévue')->assertSee('Programmer');

        // Modifier, puis programmer : la formation est créée et rattachée à la ligne
        $this->actingAs($this->rh)->put(route('plan.lignes.update', $l), ['intitule' => 'Sécurité incendie (niveau 1)', 'periode' => 'M12', 'nb_agents' => 10])->assertSessionHas('succes');
        $this->actingAs($this->rh)->post(route('plan.lignes.programmer', $l), ['date_debut' => '2026-12-07'])->assertSessionHasErrors('date_fin');
        $this->actingAs($this->rh)->post(route('plan.lignes.programmer', $l), ['date_debut' => '2026-12-07', 'date_fin' => '2026-12-08', 'lieu' => 'Siège'])->assertRedirect();
        $a = ActionFormation::firstOrFail();
        $this->assertSame($l->id, $a->ligne_plan_id);
        $this->assertSame('Sécurité incendie (niveau 1)', $a->intitule);
        $this->assertSame(10, $a->session->places);
        $this->assertSame('programmee', $l->fresh()->etat());
        $this->actingAs($this->rh)->post(route('plan.lignes.programmer', $l), ['date_debut' => '2026-12-14', 'date_fin' => '2026-12-14'])->assertSessionHas('erreur');
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertSee('Plan 2026')->assertSee('Décembre');
        // Une ligne programmée ne se retire pas
        $this->actingAs($this->rh)->delete(route('plan.lignes.destroy', $l))->assertSessionHas('erreur');
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.programmation']);
    }

    public function test_accepter_une_demande_en_l_inscrivant_au_plan_puis_la_programmer(): void
    {
        $b = $this->demande();
        $this->actingAs($this->rh)->get(route('besoins.show', $b))->assertSee('Accepter et programmer')->assertSee('Accepter et inscrire au plan');
        $this->actingAs($this->chef)->post(route('besoins.plan', $b), ['annee' => 2026, 'periode' => 'T4'])->assertForbidden();
        $this->actingAs($this->rh)->post(route('besoins.plan', $b), ['annee' => 2027, 'periode' => 'T2', 'commentaire' => 'Budget 2027'])
            ->assertRedirect(route('plan.index', ['annee' => 2027]));
        $this->assertSame(StatutBesoin::AuPlan, $b->fresh()->statut);
        $this->assertSame('Inscrite au plan', $b->fresh()->statut->libelle());
        $l = PlanLigne::firstOrFail();
        $this->assertSame([2027, 'T2', $b->id, 2, $this->service1->id], [$l->plan->annee, $l->periode, $l->besoin_id, $l->nb_agents, $l->service_id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->chef->id]);
        $this->actingAs($this->chef)->get(route('besoins.show', $b))->assertSee('Inscrite au plan annuel 2027')->assertSee('2e trimestre')->assertSee('Les dates seront fixées par la RH');
        // Une demande au plan ne se supprime pas et ne s'accepte pas une seconde fois
        $this->actingAs($this->rh)->delete(route('besoins.destroy', $b))->assertForbidden();
        $this->actingAs($this->rh)->post(route('besoins.accepter', $b), ['date_debut' => '2027-04-05', 'date_fin' => '2027-04-05'])->assertSessionHas('erreur');

        // Programmer depuis le plan : demande « Formation programmée », agents inscrits
        $this->actingAs($this->rh)->post(route('plan.lignes.programmer', $l), ['date_debut' => '2027-04-05', 'date_fin' => '2027-04-06'])->assertRedirect();
        $a = ActionFormation::firstOrFail();
        $this->assertSame([$b->id, $l->id], [$a->besoin_id, $a->ligne_plan_id]);
        $this->assertSame(StatutBesoin::Organisee, $b->fresh()->statut);
        $this->assertSame(2, $a->session->participantsActifs()->count());
        $this->assertSame(['soumission', 'inscription_plan', 'organisation'], Decision::where('decidable_id', $b->id)->orderBy('id')->pluck('action')->all());

        // Formation supprimée par erreur : la ligne redevient « Prévue » et la demande reste au plan ; restaurée, tout revient
        $this->actingAs($this->rh)->delete(route('suppression.unique', ['formations', $a->id]));
        $this->assertSame('prevue', $l->fresh()->etat());
        $this->assertSame(StatutBesoin::AuPlan, $b->fresh()->statut);
        $this->actingAs($this->rh)->post(route('corbeille.restaurer', ['formations', $a->id]));
        $this->assertSame('programmee', $l->fresh()->etat());
        $this->assertSame(StatutBesoin::Organisee, $b->fresh()->statut);
    }

    public function test_retirer_une_demande_du_plan_exige_un_motif(): void
    {
        $b = $this->demande();
        $this->actingAs($this->rh)->post(route('besoins.plan', $b), ['annee' => 2026, 'periode' => 'annee']);
        $l = PlanLigne::firstOrFail();
        $this->actingAs($this->rh)->delete(route('plan.lignes.destroy', $l))->assertSessionHasErrors('motif');
        $this->actingAs($this->rh)->delete(route('plan.lignes.destroy', $l), ['motif' => 'Reportée à l\'an prochain'])->assertSessionHas('succes');
        $this->assertSoftDeleted($l);
        $this->assertSame(StatutBesoin::Soumise, $b->fresh()->statut);
        $this->assertSame('Reportée à l\'an prochain', Decision::where('decidable_id', $b->id)->where('action', 'retrait_plan')->value('commentaire'));
        // La demande peut à nouveau être inscrite (la clé unique a été libérée)
        $this->actingAs($this->rh)->post(route('besoins.plan', $b->fresh()), ['annee' => 2027, 'periode' => 'T1'])->assertRedirect();
        $this->assertSame(1, PlanLigne::count());
    }

    public function test_validation_simple_et_exports(): void
    {
        $this->actingAs($this->rh)->post(route('plan.valider', 2026))->assertSessionHas('erreur'); // plan vide
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'Excel', 'periode' => 'T1', 'service_id' => $this->service1->id]);
        $this->actingAs($this->rh)->post(route('plan.valider', 2026), ['commentaire' => 'Conseil de direction du 20/10'])->assertSessionHas('succes');
        $p = PlanAnnuel::where('annee', 2026)->firstOrFail();
        $this->assertTrue($p->estValide());
        $this->assertSame($this->rh->id, $p->valide_par);
        $this->travel(1)->minutes();
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'Ajout tardif', 'periode' => 'T3']);
        $this->actingAs($this->rh)->get(route('plan.index'))->assertSee('Plan validé le 23/10/2026')->assertSee('Conseil de direction du 20/10')
            ->assertSee('Ajoutée après validation')->assertDontSee('Valider le plan');
        $this->actingAs($this->rh)->get(route('plan.export', [2026, 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->rh)->get(route('plan.export', [2026, 'xlsx']))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.validation']);
        $this->actingAs($this->rh)->post('/plan-annuel/99999/validation')->assertNotFound();
        $this->actingAs($this->rh)->get('/plan-annuel/1999/export/pdf')->assertNotFound();
    }

    public function test_plan_vide_n_est_plus_affiche_valide(): void
    {
        // 2.5.1 : retirer la dernière formation d'un plan validé efface la validation
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'Excel', 'periode' => 'T1']);
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'Word', 'periode' => 'T2']);
        $this->actingAs($this->rh)->post(route('plan.valider', 2026))->assertSessionHas('succes');
        [$l1, $l2] = PlanLigne::orderBy('id')->get()->all();
        $this->actingAs($this->rh)->delete(route('plan.lignes.destroy', $l1))->assertSessionHas('succes');
        $this->assertTrue(PlanAnnuel::where('annee', 2026)->firstOrFail()->estValide(), 'Il reste une formation : le plan reste validé.');
        $this->actingAs($this->rh)->delete(route('plan.lignes.destroy', $l2))->assertSessionHas('succes');
        $this->assertFalse(PlanAnnuel::where('annee', 2026)->firstOrFail()->estValide());
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.validation_annulee']);
        $this->actingAs($this->rh)->get(route('plan.index', ['annee' => 2026]))->assertOk()->assertDontSee('Plan validé le');

        // Données existantes : la migration corrige un plan validé resté vide
        $p = PlanAnnuel::where('annee', 2026)->firstOrFail();
        $p->forceFill(['valide_le' => now(), 'valide_par' => $this->rh->id])->save();
        (require database_path('migrations/2026_11_03_002400_plans_valides_vides.php'))->up();
        $this->assertFalse($p->fresh()->estValide());
    }

    public function test_le_chef_de_service_consulte_seulement_les_lignes_de_son_service(): void
    {
        foreach ([['Pour le service 1', $this->service1->id], ['Pour le service 2', $this->service2->id], ['Pour toute l\'INPS', null]] as [$i, $s]) {
            $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => $i, 'periode' => 'T1', 'service_id' => $s]);
        }
        $this->actingAs($this->chef)->get(route('plan.index')); // consomme le message flash de la RH
        $this->actingAs($this->chef)->get(route('plan.index'))->assertOk()->assertSee('Pour le service 1')
            ->assertDontSee('Pour le service 2')->assertDontSee('Pour toute l&#039;INPS', false)
            ->assertDontSee('Ajouter une formation au plan')->assertDontSee('Programmer');
        $l = PlanLigne::where('service_id', $this->service1->id)->firstOrFail();
        $this->actingAs($this->chef)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'X', 'periode' => 'T1'])->assertForbidden();
        $this->actingAs($this->chef)->post(route('plan.lignes.programmer', $l), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'])->assertForbidden();
        $this->actingAs($this->chef)->get(route('plan.export', [2026, 'xlsx']))->assertOk();
    }

    public function test_reprogrammer_une_ligne_dont_la_formation_est_annulee(): void
    {
        $this->actingAs($this->rh)->post(route('plan.lignes.store'), ['annee' => 2026, 'intitule' => 'Annulée puis reprise', 'periode' => 'T4']);
        $l = PlanLigne::firstOrFail();
        $this->actingAs($this->rh)->post(route('plan.lignes.programmer', $l), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02']);
        $s = ActionFormation::firstOrFail()->session;
        $this->actingAs($this->rh)->post(route('sessions.annuler', $s), ['motif' => 'Formateur absent']);
        $this->assertSame('annulee', $l->fresh()->etat());
        $this->actingAs($this->rh)->post(route('plan.lignes.programmer', $l), ['date_debut' => '2026-11-16', 'date_fin' => '2026-11-16'])->assertRedirect();
        $this->assertSame('programmee', $l->fresh()->etat());
        $this->assertSame(2, ActionFormation::where('ligne_plan_id', $l->id)->count());
    }
}
