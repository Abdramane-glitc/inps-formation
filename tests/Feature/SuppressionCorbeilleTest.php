<?php

namespace Tests\Feature;

use App\Enums\StatutBesoin;
use App\Enums\StatutSession;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\BesoinFormation;
use App\Models\Decision;
use App\Models\User;
use App\Services\ActionService;
use App\Services\AttestationService;
use App\Services\BesoinService;
use App\Services\InscriptionService;
use App\Services\RapportService;
use Tests\TestCase;

/** Suppression des erreurs de saisie, cases à cocher et corbeille (version 1.6.0). */
class SuppressionCorbeilleTest extends TestCase
{
    private User $rh;

    private User $chef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rh = $this->utilisateur('responsable_drh');
        $this->chef = $this->utilisateur('chef_service');
    }

    private function agents(int $n): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = Agent::create(['matricule' => 'SUP'.$i.uniqid(), 'nom' => 'SUPPR'.$i, 'prenom' => 'P', 'service_id' => $this->service1->id])->id;
        }

        return $ids;
    }

    private function demande(string $intitule = 'Demande', ?array $agents = null): BesoinFormation
    {
        return app(BesoinService::class)->creer(['intitule' => $intitule, 'contexte' => 'X'], $this->chef, $agents ?? $this->agents(2));
    }

    public function test_suppression_d_une_demande_selon_son_etat_et_restauration(): void
    {
        $b = $this->demande('Erreur de saisie');
        $this->actingAs($this->chef)->get(route('besoins.show', $b))->assertSee('Supprimer la demande');
        $this->actingAs($this->chef)->delete(route('besoins.destroy', $b))->assertRedirect(route('besoins.index'));
        $this->assertSoftDeleted($b);
        $this->assertDatabaseHas('audit_logs', ['action' => 'besoin.suppression', 'objet_id' => $b->id]);
        $this->actingAs($this->chef)->get(route('besoins.index'))->assertDontSee(route('besoins.show', $b), false);

        // Après un refus de la RH, le chef ne peut plus supprimer ; la RH le peut
        $refusee = $this->demande('Refusée');
        app(BesoinService::class)->transition($refusee, 'rejet', $this->rh, 'Non');
        $this->actingAs($this->chef)->get(route('besoins.show', $refusee))->assertDontSee('Supprimer la demande');
        $this->actingAs($this->chef)->delete(route('besoins.destroy', $refusee))->assertForbidden();
        $this->actingAs($this->rh)->delete(route('besoins.destroy', $refusee))->assertRedirect();
        $this->assertSoftDeleted($refusee);

        // Une demande avec formation programmée ne se supprime pas (il faut supprimer la formation)
        $prog = $this->demande('Programmée');
        app(ActionService::class)->accepterEtProgrammer($prog, ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'], $this->rh);
        $this->actingAs($this->rh)->delete(route('suppression.unique', ['besoins', $prog->id]))->assertSessionHas('erreur', fn ($m) => str_contains($m, 'supprimez d\'abord la formation'));

        // Corbeille réservée à la RH ; restauration
        $this->actingAs($this->chef)->get(route('corbeille.index'))->assertForbidden();
        $this->actingAs($this->rh)->get(route('corbeille.index'))->assertOk()->assertSee($b->reference)->assertSee($refusee->reference);
        $this->actingAs($this->rh)->post(route('corbeille.restaurer', ['besoins', $b->id]))->assertRedirect(route('besoins.show', $b));
        $this->assertNotSoftDeleted($b);
        $this->assertSame(StatutBesoin::Soumise, $b->fresh()->statut);
        $this->assertSame(['soumission', 'suppression', 'restauration'], Decision::where('decidable_id', $b->id)->where('decidable_type', 'besoin')->orderBy('id')->pluck('action')->all());
    }

    public function test_suppression_d_une_formation_creee_par_erreur_puis_restauration(): void
    {
        $agents = $this->agents(2);
        $b = $this->demande('Mauvaises dates', $agents);
        $this->actingAs($this->rh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-03']);
        $a = ActionFormation::firstOrFail();
        $s = $a->session;

        $this->actingAs($this->chef)->delete(route('suppression.unique', ['formations', $a->id]))->assertForbidden();
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertSee('Supprimer');
        $this->actingAs($this->rh)->delete(route('suppression.unique', ['formations', $a->id]))->assertRedirect(route('formations.index'));

        $this->assertSoftDeleted($a);
        $this->assertSame(StatutSession::Annulee, $s->fresh()->statut);
        $this->assertSame(0, $s->participantsActifs()->count(), 'Les participants ne bloquent plus leurs dates.');
        $this->assertSame(StatutBesoin::Soumise, $b->fresh()->statut, 'La demande attend à nouveau une décision.');
        $this->assertNull($a->fresh()->besoin_id);
        $this->actingAs($this->rh)->get(route('formations.show', $a))->assertNotFound();
        $this->actingAs($this->rh)->get(route('formations.index'))->assertDontSee($a->reference);
        $this->assertCount(0, app(RapportService::class)->generer('formations_annee', ['annee' => 2026])['lignes']);
        $this->actingAs($this->rh)->get(route('besoins.show', $b))->assertSee('Formation supprimée')->assertSee('Accepter et programmer');

        // Restauration : formation, participants et lien avec la demande retrouvés
        $this->actingAs($this->rh)->get(route('corbeille.index'))->assertSee($a->reference)->assertSee($this->rh->name);
        $this->actingAs($this->rh)->post(route('corbeille.restaurer', ['formations', $a->id]))->assertRedirect(route('formations.show', $a));
        $this->assertNotSoftDeleted($a);
        $this->assertSame(StatutSession::Planifiee, $s->fresh()->statut);
        $this->assertSame(2, $s->participantsActifs()->count());
        $this->assertSame($b->id, $a->fresh()->besoin_id);
        $this->assertSame(StatutBesoin::Organisee, $b->fresh()->statut);
    }

    public function test_reprogrammer_une_demande_apres_suppression_de_sa_formation(): void
    {
        $b = $this->demande('Reprogrammée');
        $svc = app(ActionService::class);
        $a1 = $svc->accepterEtProgrammer($b, ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'], $this->rh);
        $this->actingAs($this->rh)->delete(route('suppression.unique', ['formations', $a1->id]));
        // Nouvelle acceptation avec d'autres dates : aucun conflit avec la formation supprimée
        $this->actingAs($this->rh)->post(route('besoins.accepter', $b->fresh()), ['date_debut' => '2026-11-09', 'date_fin' => '2026-11-09'])->assertRedirect();
        $a2 = ActionFormation::where('besoin_id', $b->id)->firstOrFail();
        $this->assertSame(2, $a2->session->participantsActifs()->count());
        // Restaurer l'ancienne : elle revient sans demande, celle-ci ayant déjà une autre formation
        $this->actingAs($this->rh)->post(route('corbeille.restaurer', ['formations', $a1->id]));
        $this->assertNull($a1->fresh()->besoin_id);
        $this->assertSame($a2->id, $b->fresh()->formation->id);
    }

    public function test_formation_terminee_ou_avec_attestation_non_supprimable(): void
    {
        $s = $this->nouvelleFormation($this->rh);
        app(InscriptionService::class)->inscrire($s, $this->agents(1), $this->rh);
        app(ActionService::class)->terminer($s, $s->participantsActifs()->pluck('id')->all(), $this->rh);
        app(AttestationService::class)->emettre($s->participantsActifs()->first(), $this->rh);
        $this->actingAs($this->rh)->get(route('formations.show', $s->action))->assertDontSee('>Supprimer<', false);
        $this->actingAs($this->rh)->delete(route('suppression.unique', ['formations', $s->action_id]))
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'terminée'));
        $this->assertNotSoftDeleted($s->action);
    }

    public function test_suppression_de_la_selection_dans_les_listes(): void
    {
        $libre = $this->nouvelleFormation($this->rh, ['intitule' => 'Libre']);
        $finie = $this->nouvelleFormation($this->rh, ['intitule' => 'Finie', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-01']);
        app(InscriptionService::class)->inscrire($finie, $this->agents(1), $this->rh);
        app(ActionService::class)->terminer($finie, [], $this->rh);

        $this->actingAs($this->rh)->get(route('formations.index'))->assertSee('Supprimer la sélection')->assertSee('name="ids[]"', false);
        $this->actingAs($this->rh)->post(route('suppression.selection', 'formations'), [])->assertSessionHasErrors('ids');
        $this->actingAs($this->rh)->post(route('suppression.selection', 'formations'), ['ids' => [$libre->action_id, $finie->action_id]])
            ->assertSessionHas('succes', '1 élément(s) supprimé(s) sur 2.')
            ->assertSessionHas('avertissements', fn ($av) => count($av) === 1 && str_contains($av[0], $finie->action->reference));
        $this->assertSoftDeleted($libre->action);

        // Agents : seul celui qui n'a jamais servi est supprimé (définitivement)
        [$orphelin, $inscrit] = $this->agents(2);
        app(InscriptionService::class)->inscrire($this->nouvelleFormation($this->rh, ['date_debut' => '2026-12-01', 'date_fin' => '2026-12-01']), [$inscrit], $this->rh);
        $this->actingAs($this->chef)->post(route('suppression.selection', 'agents'), ['ids' => [$orphelin]])
            ->assertSessionHas('avertissements', fn ($av) => str_contains($av[0], 'accès refusé'));
        $this->actingAs($this->rh)->post(route('suppression.selection', 'agents'), ['ids' => [$orphelin, $inscrit]])
            ->assertSessionHas('avertissements', fn ($av) => str_contains($av[0], 'désactivez-le plutôt'));
        $this->assertDatabaseMissing('agents', ['id' => $orphelin]);
        $this->assertDatabaseHas('agents', ['id' => $inscrit]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agent.suppression']);

        // Le responsable de structure coche ses demandes non décidées
        $d1 = $this->demande('A');
        $d2 = $this->demande('B');
        app(BesoinService::class)->transition($d2, 'rejet', $this->rh, 'Non');
        $this->actingAs($this->chef)->post(route('suppression.selection', 'besoins'), ['ids' => [$d1->id, $d2->id]])->assertSessionHas('succes', '1 élément(s) supprimé(s) sur 2.');
        $this->assertSoftDeleted($d1);
    }

    public function test_suppression_d_un_compte_jamais_utilise(): void
    {
        $admin = $this->utilisateur('admin_technique');
        $neuf = $this->utilisateur('chef_service');
        $utilise = $this->utilisateur('chef_service');
        $utilise->forceFill(['derniere_connexion_le' => now()])->save();

        $this->actingAs($admin)->get(route('admin.utilisateurs.edit', $neuf))->assertSee('Supprimer le compte');
        $this->actingAs($admin)->get(route('admin.utilisateurs.edit', $utilise))->assertDontSee('Supprimer le compte');
        $this->actingAs($admin)->delete(route('suppression.unique', ['comptes', $utilise->id]))->assertSessionHas('erreur', fn ($m) => str_contains($m, 'désactivez-le'));
        $this->actingAs($admin)->delete(route('suppression.unique', ['comptes', $admin->id]))->assertSessionHas('erreur', fn ($m) => str_contains($m, 'votre propre compte'));
        $this->actingAs($admin)->delete(route('suppression.unique', ['comptes', $neuf->id]))->assertRedirect(route('admin.utilisateurs.index'));
        $this->assertDatabaseMissing('users', ['id' => $neuf->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'utilisateur.suppression']);
        $this->actingAs($this->chef)->delete(route('suppression.unique', ['comptes', $utilise->id]))->assertForbidden();
    }

    public function test_agent_non_inscrit_mais_dans_une_demande_non_supprimable(): void
    {
        [$ag] = $this->agents(1);
        $this->demande('Avec agent', [$ag]);
        $this->actingAs($this->rh)->get(route('agents.show', $ag))->assertDontSee('>Supprimer<', false);
        $this->actingAs($this->rh)->delete(route('suppression.unique', ['agents', $ag]))->assertSessionHas('erreur', fn ($m) => str_contains($m, 'cité dans une demande'));
        $this->assertTrue(Agent::whereKey($ag)->exists());
    }
}
