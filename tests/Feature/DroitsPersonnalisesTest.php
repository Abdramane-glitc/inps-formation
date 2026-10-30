<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Profils;
use Tests\TestCase;

/**
 * 2.0.0 : droits personnalisables par compte, à partir du profil (RH ou Responsable de structure).
 * Garde-fous : on ne donne pas un droit qu'on n'a pas ; les droits d'administration exigent « roles.gerer » ;
 * ses propres droits ne se modifient pas.
 */
class DroitsPersonnalisesTest extends TestCase
{
    private function compteRh(): User
    {
        $u = $this->utilisateur('responsable_drh');
        $u->roles()->attach(Role::where('code', 'admin_technique')->value('id'));

        return $u->fresh();
    }

    private function envoi(User $u, array $droits = [], bool $personnaliser = true, string $profil = 'chef_service'): array
    {
        return ['name' => $u->name, 'email' => $u->email, 'profil' => $profil, 'service_id' => $u->service_id]
            + ($personnaliser ? ['personnaliser' => 1, 'droits' => $droits] : []);
    }

    public function test_la_rh_ajoute_et_retire_des_droits_a_un_chef_de_service(): void
    {
        $rh = $this->compteRh();
        $chef = $this->utilisateur('chef_service');
        $this->actingAs($chef)->get(route('rapports.index'))->assertForbidden();
        $this->actingAs($rh)->get(route('admin.utilisateurs.edit', $chef))->assertOk()->assertSee('Personnaliser les droits de ce compte');

        // Profil responsable de structure + « rapports », sans « évaluer la mise en pratique »
        $droits = array_values(array_diff(array_merge(Profils::droitsDuProfil('chef_service'), ['rapports.voir']), ['evaluations.froid']));
        $this->actingAs($rh)->put(route('admin.utilisateurs.update', $chef), $this->envoi($chef, $droits))->assertSessionHas('succes');
        $chef = $chef->fresh();
        $this->assertSame(['evaluations.froid' => false, 'rapports.voir' => true], collect($chef->droitsPersonnalises())->sortKeys()->all());
        $this->assertTrue($chef->aPermission('rapports.voir'));
        $this->assertFalse($chef->aPermission('evaluations.froid'));
        $this->assertTrue($chef->aPermission('besoins.creer'), 'Les autres droits du profil sont conservés.');
        $this->actingAs($chef)->get(route('rapports.index'))->assertOk();
        $this->actingAs($chef)->get(route('evaluations.index'))->assertForbidden();
        $this->assertStringContainsString('(droits personnalisés)', Profils::affichage($chef));
        $this->assertDatabaseHas('audit_logs', ['action' => 'utilisateur.droits_personnalises', 'objet_id' => $chef->id]);
        $this->assertTrue(app(NotificationService::class)->utilisateursAvecPermission('rapports.voir')->contains('id', $chef->id), 'Les notifications suivent les droits effectifs.');
        $this->actingAs($rh)->get(route('admin.roles.index'))->assertOk()->assertSee('Comptes aux droits personnalisés (1)')->assertSee($chef->email);

        // Décocher « Personnaliser » : retour exact au profil
        $this->actingAs($rh)->put(route('admin.utilisateurs.update', $chef), $this->envoi($chef, [], false))->assertSessionHas('succes');
        $chef = $chef->fresh();
        $this->assertSame([], $chef->droitsPersonnalises());
        $this->assertFalse($chef->aPermission('rapports.voir'));
        $this->assertTrue($chef->aPermission('evaluations.froid'));
    }

    public function test_creation_d_un_compte_avec_des_droits_personnalises(): void
    {
        $rh = $this->compteRh();
        $this->actingAs($rh)->post(route('admin.utilisateurs.store'), ['name' => 'Assistante RH', 'email' => 'assistante@exemple.invalid', 'profil' => 'chef_service',
            'service_id' => $this->service1->id, 'personnaliser' => 1, 'droits' => ['besoins.voir_tous', 'actions.voir', 'inscriptions.gerer']])->assertRedirect();
        $u = User::where('email', 'assistante@exemple.invalid')->firstOrFail();
        $this->assertTrue($u->aPermission('inscriptions.gerer'));
        $this->assertTrue($u->aPermission('besoins.voir_tous'));
        $this->assertFalse($u->aPermission('besoins.creer'), 'Droit du profil décoché à la création.');
        // Un droit inconnu est refusé
        $this->actingAs($rh)->post(route('admin.utilisateurs.store'), ['name' => 'X', 'email' => 'x@exemple.invalid', 'profil' => 'rh', 'personnaliser' => 1, 'droits' => ['tout.faire']])
            ->assertSessionHasErrors('droits.0');
    }

    public function test_on_ne_donne_pas_un_droit_qu_on_n_a_pas(): void
    {
        // Un administrateur technique seul gère les comptes mais n'a pas accès aux rapports
        $admin = $this->utilisateur('admin_technique');
        $chef = $this->utilisateur('chef_service');
        $this->actingAs($admin)->put(route('admin.utilisateurs.update', $chef), $this->envoi($chef, array_merge(Profils::droitsDuProfil('chef_service'), ['rapports.voir'])))
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'que vous n\'avez pas vous-même') && str_contains($m, 'Consulter et exporter les rapports'));
        $this->assertSame([], $chef->fresh()->droitsPersonnalises());

        // Les droits d'administration exigent « roles.gerer », même si on les détient
        $gest = $this->utilisateur('chef_service');
        $gest->permissionsPersonnalisees()->attach(Permission::where('code', 'utilisateurs.gerer')->value('id'), ['accorde' => true]);
        $gest = $gest->fresh();
        $this->assertTrue($gest->aPermission('utilisateurs.gerer'));
        $this->actingAs($gest)->put(route('admin.utilisateurs.update', $chef), $this->envoi($chef, array_merge(Profils::droitsDuProfil('chef_service'), ['utilisateurs.gerer'])))
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'droits d\'administration'));
        $this->assertFalse($chef->fresh()->aPermission('utilisateurs.gerer'));
    }

    public function test_ses_propres_droits_ne_changent_pas(): void
    {
        $rh = $this->compteRh();
        $this->actingAs($rh)->put(route('admin.utilisateurs.update', $rh), ['name' => 'Moi', 'email' => $rh->email, 'profil' => 'rh', 'personnaliser' => 1, 'droits' => ['actions.voir']])
            ->assertSessionHas('succes');
        $rh = $rh->fresh();
        $this->assertSame('Moi', $rh->name);
        $this->assertSame([], $rh->droitsPersonnalises());
        $this->assertTrue($rh->aPermission('utilisateurs.gerer'));
    }
}
