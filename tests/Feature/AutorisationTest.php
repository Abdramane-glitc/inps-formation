<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BesoinFormation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\BesoinService;
use App\Support\Profils;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutorisationTest extends TestCase
{
    private function besoin(int $serviceId, User $auteur): BesoinFormation
    {
        return app(BesoinService::class)->creer(['service_id' => $serviceId, 'intitule' => 'Besoin test', 'contexte' => 'Test'], $auteur);
    }

    public function test_compte_sans_role_n_a_acces_a_rien(): void
    {
        $u = new User(['name' => 'Sans rôle', 'email' => 'vide@exemple.invalid', 'password' => 'Mot-de-passe-Test-2026!']);
        $u->actif = true;
        $u->save();
        $this->actingAs($u)->get('/')->assertOk()->assertSee('aucun droit');
        foreach (['/tableau-de-bord', '/agents', '/besoins', '/formations', '/rapports', '/administration/utilisateurs', '/administration/audit', '/administration/parametres', '/mon-espace'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }
    }

    public function test_profil_agent_limite_a_son_espace(): void
    {
        $u = $this->utilisateur('agent');
        $this->actingAs($u)->get('/')->assertRedirect(route('espace.index'));
        $this->actingAs($u)->get('/mon-espace')->assertOk();
        foreach (['/tableau-de-bord', '/agents', '/besoins', '/administration/roles', '/rapports'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }
    }

    public function test_auditeur_consulte_sans_pouvoir_modifier(): void
    {
        $aud = $this->utilisateur('auditeur');
        $this->actingAs($aud)->get('/besoins')->assertOk();
        $this->actingAs($aud)->get('/administration/audit')->assertOk();
        $this->actingAs($aud)->post('/agents', ['matricule' => 'X1', 'nom' => 'A', 'prenom' => 'B'])->assertForbidden();
        $this->actingAs($aud)->get('/formations')->assertOk();
        $this->actingAs($aud)->post('/formations', [])->assertForbidden();
        $this->actingAs($aud)->get('/besoins/creer')->assertForbidden();
        $this->actingAs($aud)->put('/administration/parametres', [])->assertForbidden();
    }

    public function test_cloisonnement_des_besoins_par_service(): void
    {
        $chef1 = $this->utilisateur('chef_service', $this->service1);
        $chef2 = $this->utilisateur('chef_service', $this->service2);
        $b1 = $this->besoin($this->service1->id, $chef1);
        $b2 = $this->besoin($this->service2->id, $chef2);
        $this->actingAs($chef1)->get(route('besoins.show', $b1))->assertOk();
        $this->actingAs($chef1)->get(route('besoins.show', $b2))->assertForbidden();
        $this->actingAs($chef1)->get(route('besoins.edit', $b2))->assertForbidden();
        $this->actingAs($chef1)->post(route('besoins.transition', $b2), ['action' => 'soumission'])->assertForbidden();
        $this->actingAs($chef1)->post(route('documents.store', ['besoin', $b2->id]), [])->assertForbidden();
        $this->actingAs($chef1)->get('/besoins')->assertOk()->assertSee($b1->reference)->assertDontSee($b2->reference);
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->get('/besoins')->assertSee($b1->reference)->assertSee($b2->reference);
    }

    public function test_chef_de_service_ne_peut_declarer_que_pour_son_service(): void
    {
        $chef1 = $this->utilisateur('chef_service', $this->service1);
        $ag = Agent::create(['matricule' => 'S1-T', 'nom' => 'UN', 'prenom' => 'A', 'service_id' => $this->service1->id]);
        $this->actingAs($chef1)->post('/besoins', $this->analyse() + ['service_id' => $this->service2->id, 'intitule' => 'Tentative', 'contexte' => 'X', 'agents' => [$ag->id]])->assertSessionHas('erreur');
        $this->assertDatabaseMissing('besoins_formation', ['intitule' => 'Tentative']);
    }

    public function test_chef_de_service_ne_peut_pas_instruire(): void
    {
        $chef = $this->utilisateur('chef_service');
        $b = $this->besoin($this->service1->id, $chef);
        $this->actingAs($chef)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-02'])->assertForbidden();
        $this->actingAs($chef)->post(route('besoins.transition', $b), ['action' => 'rejet', 'commentaire' => 'Non'])->assertForbidden();
        $this->assertSame('soumise', $b->fresh()->statut->value);
    }

    public function test_cloisonnement_des_agents_par_service(): void
    {
        $a1 = Agent::create(['matricule' => 'S1-001', 'nom' => 'UN', 'prenom' => 'A', 'service_id' => $this->service1->id]);
        $a2 = Agent::create(['matricule' => 'S2-001', 'nom' => 'DEUX', 'prenom' => 'B', 'service_id' => $this->service2->id]);
        $chef = $this->utilisateur('chef_service', $this->service1);
        $this->actingAs($chef)->get(route('agents.show', $a1))->assertOk();
        $this->actingAs($chef)->get(route('agents.show', $a2))->assertForbidden();
        $this->actingAs($chef)->get('/agents')->assertSee('S1-001')->assertDontSee('S2-001');
    }

    public function test_un_agent_ne_voit_pas_l_attestation_d_un_autre(): void
    {
        $this->actingAs($this->utilisateur('agent'))->get('/mon-espace/attestations/999')->assertNotFound();
    }

    public function test_un_administrateur_ne_peut_pas_modifier_ses_propres_roles(): void
    {
        $admin = $this->utilisateur('admin_technique');
        $drh = Role::where('code', 'responsable_drh')->value('id');
        $this->actingAs($admin)->put(route('admin.utilisateurs.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'profil' => 'rh'])
            ->assertSessionHas('erreur', 'Vous ne pouvez pas modifier votre propre profil.');
        $this->assertFalse($admin->fresh()->roles->contains('id', $drh));
        // Un champ « roles » envoyé à la main est ignoré
        $this->actingAs($admin)->put(route('admin.utilisateurs.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'profil' => 'conserver', 'roles' => [$drh]])
            ->assertSessionHas('succes');
        $this->assertFalse($admin->fresh()->roles->contains('id', $drh));
    }

    public function test_attribution_d_un_role_d_administration_reservee_a_roles_gerer(): void
    {
        $r = Role::create(['code' => 'gestion_comptes', 'nom' => 'Gestion des comptes seulement']);
        $r->permissions()->sync(Permission::where('code', 'utilisateurs.gerer')->pluck('id'));
        $gest = $this->utilisateur('gestion_comptes');
        $cible = $this->utilisateur('auditeur'); // un compte d'agent se gère depuis Personnel (2.3.0)
        $this->actingAs($gest)->put(route('admin.utilisateurs.update', $cible), ['name' => $cible->name, 'email' => $cible->email,
            'profil' => 'rh'])->assertForbidden();
        $this->assertFalse($cible->fresh()->roles->contains('code', 'admin_technique'));
        // Un rôle métier reste attribuable
        $this->actingAs($gest)->put(route('admin.utilisateurs.update', $cible), ['name' => $cible->name, 'email' => $cible->email,
            'profil' => 'chef_service', 'service_id' => $this->service1->id])->assertSessionHas('succes');
        $this->assertSame(['chef_service'], $cible->fresh()->roles->pluck('code')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'utilisateur.droits', 'objet_id' => $cible->id]);
    }

    public function test_le_dernier_administrateur_technique_ne_peut_etre_desactive(): void
    {
        $admin1 = $this->utilisateur('admin_technique');
        $gest = Role::create(['code' => 'comptes', 'nom' => 'Comptes']);
        $gest->permissions()->sync(Permission::whereIn('code', ['utilisateurs.gerer', 'roles.gerer'])->pluck('id'));
        $autre = $this->utilisateur('comptes');
        $this->actingAs($autre)->post(route('admin.utilisateurs.activation', $admin1), ['actif' => 0])->assertStatus(422);
        $this->assertTrue($admin1->fresh()->actif);
        $this->actingAs($admin1)->post(route('admin.utilisateurs.activation', $admin1), ['actif' => 0])->assertSessionHas('erreur');
    }

    public function test_les_roles_ne_sont_plus_modifiables_depuis_l_application(): void
    {
        $admin = $this->utilisateur('admin_technique');
        $role = Role::where('code', 'admin_technique')->first();
        foreach (['admin.roles.store', 'admin.roles.edit', 'admin.roles.update', 'admin.roles.destroy'] as $nom) {
            $this->assertFalse(\Route::has($nom), $nom);
        }
        $this->actingAs($admin)->put('/administration/roles/'.$role->id, ['nom' => 'X', 'permissions' => Permission::pluck('id')->all()])->assertNotFound();
        $this->assertFalse($admin->fresh()->aPermission('actions.gerer'));
        // Lecture seule : qui peut faire quoi
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk()->assertSee('Qui peut faire quoi')
            ->assertSee('Décider des demandes')->assertDontSee('Nouveau rôle')->assertDontSee('Supprimer');
    }

    public function test_desactivation_d_un_compte_ferme_ses_sessions(): void
    {
        $admin = $this->utilisateur('admin_technique');
        $cible = $this->utilisateur('agent');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $cible->id, 'payload' => 'x', 'last_activity' => time()]);
        $this->actingAs($admin)->post(route('admin.utilisateurs.activation', $cible), ['actif' => 0])->assertSessionHas('succes');
        $this->assertFalse($cible->fresh()->actif);
        $this->assertDatabaseMissing('sessions', ['user_id' => $cible->id]);
    }

    public function test_mot_de_passe_temporaire_genere_par_l_administrateur(): void
    {
        $admin = $this->utilisateur('admin_technique');
        $this->actingAs($admin)->post(route('admin.utilisateurs.store'), ['name' => 'Sans service', 'email' => 'x@exemple.invalid', 'profil' => 'chef_service'])
            ->assertSessionHasErrors('service_id');
        $this->actingAs($admin)->post(route('admin.utilisateurs.store'), ['name' => 'Nouveau', 'email' => 'nouveau@exemple.invalid', 'profil' => 'chef_service', 'service_id' => $this->service1->id])
            ->assertSessionHas('mot_de_passe_temporaire');
        $u = User::where('email', 'nouveau@exemple.invalid')->first();
        $this->assertTrue($u->doit_changer_mot_de_passe);
        $this->assertTrue($u->aPermission('besoins.creer'));
        $this->assertSame($this->service1->direction_id, $u->direction_id, 'Direction déduite du service.');
        $this->assertSame('Responsable de structure', Profils::affichage($u));
    }

    public function test_permission_retiree_du_role_prend_effet(): void
    {
        $drh = $this->utilisateur('responsable_drh');
        $this->actingAs($drh)->get('/rapports')->assertOk();
        Role::where('code', 'responsable_drh')->first()->permissions()->detach(Permission::where('code', 'rapports.voir')->value('id'));
        $this->actingAs($drh->fresh())->get('/rapports')->assertForbidden();
    }
}
