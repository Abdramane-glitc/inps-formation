<?php

namespace Tests\Feature;

use App\Enums\StatutAction;
use App\Enums\StatutBesoin;
use App\Models\ActionFormation;
use App\Models\Agent;
use App\Models\BesoinFormation;
use App\Models\Parametre;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\BesoinService;
use App\Services\RapportService;
use App\Support\Profils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Organisation retenue par l'INPS : la RH gère tout, le responsable de structure déclare les besoins.
 * Parcours simplifié (1.3.0) : demande → acceptée → formation organisée, sans plan annuel.
 */
class OrganisationDeuxProfilsTest extends TestCase
{
    private function compteRh(): User
    {
        $u = $this->utilisateur('responsable_drh');
        $u->roles()->attach(Role::where('code', 'admin_technique')->value('id'));

        return $u->fresh();
    }

    public function test_le_referentiel_initial_ne_comporte_plus_de_plan_annuel(): void
    {
        $this->assertSame(0, Permission::where('code', 'like', 'plans.%')->count());
        $this->assertNull(Parametre::where('cle', 'plans.interdire_auto_validation')->first());
        $this->assertFalse(Route::has('plans.index'));
        $this->assertFalse(Route::has('admin.circuits.index'));
        foreach (['actions.gerer', 'besoins.instruire', 'parametres.gerer', 'rapports.voir'] as $code) {
            $this->assertTrue($this->utilisateur('responsable_drh')->aPermission($code), $code);
        }
        $rh = $this->compteRh();
        $this->actingAs($rh)->get('/plans')->assertNotFound();
        $this->actingAs($rh)->get(route('tableau-bord'))->assertOk()->assertDontSee('Plans annuels')->assertSee('demandes à organiser');
    }

    public function test_la_rh_organise_seule_la_formation_demandee(): void
    {
        $rh = $this->compteRh();
        $chef = $this->utilisateur('chef_service');
        $ag = Agent::create(['matricule' => 'ODP-1', 'nom' => 'ACCUEIL', 'prenom' => 'A', 'service_id' => $this->service1->id]);
        $b = app(BesoinService::class)->creer(['intitule' => 'Accueil des assurés', 'contexte' => 'Réclamations'], $chef, [$ag->id]);
        $this->actingAs($rh)->get(route('besoins.show', $b))->assertOk()->assertSee('Accepter et programmer')->assertDontSee('Organiser la formation');
        $this->actingAs($rh)->post(route('besoins.accepter', $b), ['date_debut' => '2026-11-02', 'date_fin' => '2026-11-03'])->assertRedirect();
        $this->assertSame(StatutBesoin::Organisee, $b->fresh()->statut);
        $a = ActionFormation::firstOrFail();
        $this->assertSame($b->id, $a->besoin_id);
        $this->assertSame([$ag->id], $a->session->participantsActifs()->pluck('agent_id')->all());
    }

    public function test_la_rh_administre_et_l_application_ne_gere_plus_d_argent(): void
    {
        $rh = $this->compteRh();
        $this->actingAs($rh)->get(route('admin.utilisateurs.index'))->assertOk();
        $this->actingAs($rh)->get(route('admin.parametres.index'))->assertOk();
        $this->actingAs($rh)->get(route('admin.systeme.index'))->assertOk();
        $this->actingAs($rh)->post(route('admin.utilisateurs.store'), ['name' => 'Chef Pensions', 'email' => 'chef.pensions@exemple.invalid',
            'service_id' => $this->service1->id, 'profil' => 'chef_service'])->assertRedirect();
        $this->assertTrue(User::where('email', 'chef.pensions@exemple.invalid')->firstOrFail()->aRole('chef_service'));

        // Plus de budget ni de dépenses : ni menu, ni route, ni permission, ni montant à l'écran
        $this->assertSame(0, Permission::where('code', 'like', 'finances.%')->count());
        $this->assertNull(Role::where('code', 'gestionnaire_financier')->first());
        $this->assertFalse(Route::has('depenses.index') || Route::has('depenses.create'));
        $this->actingAs($rh)->get('/finances')->assertNotFound();
        $a = $this->nouvelleFormation($rh, ['intitule' => 'Sans coût'])->action;
        foreach ([route('tableau-bord'), route('formations.show', $a), route('formations.create'), route('besoins.create'), route('rapports.index')] as $url) {
            $this->actingAs($rh)->get($url)->assertOk()->assertDontSee('FCFA')->assertDontSee('Dépense')->assertDontSee('Budget')->assertDontSee('Finances');
        }
        $this->assertArrayNotHasKey('budget', RapportService::RAPPORTS);
        $this->assertArrayNotHasKey('cout_moyen', RapportService::RAPPORTS);
    }

    public function test_creation_du_premier_compte_rh_en_console(): void
    {
        $this->artisan('inps:creer-admin', ['--email' => 'rh@exemple.invalid', '--nom' => 'Responsable RH', '--drh' => true])
            ->expectsQuestion('Mot de passe (saisie masquée)', 'Rh-Solide-2026!')
            ->expectsQuestion('Confirmation du mot de passe', 'Rh-Solide-2026!')
            ->assertSuccessful();
        $u = User::where('email', 'rh@exemple.invalid')->firstOrFail();
        $this->assertTrue($u->aRole('admin_technique') && $u->aRole('responsable_drh'));
        $this->assertTrue($u->aPermission('utilisateurs.gerer') && $u->aPermission('actions.gerer') && $u->aPermission('besoins.instruire'));
    }

    public function test_le_chef_de_service_ne_fait_que_les_demandes(): void
    {
        $chef = $this->utilisateur('chef_service');
        $this->actingAs($chef)->get(route('besoins.create'))->assertOk();
        $this->actingAs($chef)->get(route('formations.index'))->assertOk(); // il suit les formations
        foreach (['formations.create', 'admin.utilisateurs.index', 'admin.parametres.index', 'tableau-bord'] as $route) {
            $this->actingAs($chef)->get(route($route))->assertForbidden();
        }
        $this->actingAs($chef)->post(route('formations.store'), ['intitule' => 'X', 'type' => 'interne', 'modalite' => 'presentiel',
            'date_debut' => '2026-11-02', 'date_fin' => '2026-11-02', 'places' => 5])->assertForbidden();
        $this->assertEqualsCanonicalizing(['agents.voir_service', 'besoins.voir_service', 'besoins.creer', 'actions.voir', 'evaluations.froid'],
            Role::where('code', 'chef_service')->firstOrFail()->permissions()->pluck('code')->all(), 'Le responsable de structure ne saisit pas de présences ; il évalue la mise en pratique (1.9.0).');
    }

    public function test_la_migration_1_3_0_reprend_une_base_existante_sans_perte(): void
    {
        // Base de la version 1.2.0 : un plan validé dont une ligne vient d'une demande, une action à deux sessions,
        // une demande « intégrée au plan » sans formation, la permission et le paramètre du plan.
        $chef = $this->utilisateur('chef_service');
        $rh = $this->utilisateur('responsable_drh');
        $mk = fn (string $ref, string $statut) => DB::table('besoins_formation')->insertGetId(['reference' => $ref, 'annee' => 2026, 'service_id' => $this->service1->id,
            'intitule' => $ref, 'statut' => $statut, 'auteur_id' => $chef->id, 'created_at' => now(), 'updated_at' => now()]);
        $b1 = $mk('BES-2026-0901', 'integree_plan');
        $b2 = $mk('BES-2026-0902', 'integree_plan');
        $plan = DB::table('plans_formation')->insertGetId(['annee' => 2026, 'titre' => 'Plan 2026', 'cree_par' => $rh->id, 'created_at' => now(), 'updated_at' => now()]);
        $v = DB::table('plan_versions')->insertGetId(['plan_id' => $plan, 'numero' => 1, 'statut' => 'validee', 'cree_par' => $rh->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('plan_actions')->insert([['plan_version_id' => $v, 'code' => 'PA2026-001', 'besoin_id' => $b1, 'intitule' => 'L1', 'created_at' => now(), 'updated_at' => now()],
            ['plan_version_id' => $v, 'code' => 'PA2026-002', 'besoin_id' => $b2, 'intitule' => 'L2', 'created_at' => now(), 'updated_at' => now()]]);
        $a = DB::table('actions_formation')->insertGetId(['reference' => 'ACT-2026-0901', 'annee' => 2026, 'intitule' => 'Deux groupes', 'type' => 'interne', 'statut' => 'realisee',
            'plan_id' => $plan, 'plan_action_code' => 'PA2026-001', 'cree_par' => $rh->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['S01', '2026-03-02', 'terminee'], ['S02', '2026-06-01', 'planifiee']] as [$c, $d, $st]) {
            DB::table('sessions_formation')->insert(['action_id' => $a, 'code' => 'ACT-2026-0901-'.$c, 'date_debut' => $d, 'date_fin' => $d, 'places' => 5, 'statut' => $st, 'created_at' => now(), 'updated_at' => now()]);
        }
        $perm = DB::table('permissions')->insertGetId(['code' => 'plans.voir', 'module' => 'Plans', 'libelle' => 'Consulter les plans', 'sensible' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['role_id' => Role::where('code', 'responsable_drh')->value('id'), 'permission_id' => $perm]);
        DB::table('parametres')->insert(['cle' => 'plans.interdire_auto_validation', 'valeur' => '0', 'type' => 'bool', 'groupe' => 'regles', 'libelle' => 'X', 'created_at' => now(), 'updated_at' => now()]);

        (require database_path('migrations/2026_10_21_000900_formations_sans_plan_annuel.php'))->up();

        $this->assertSame($b1, ActionFormation::findOrFail($a)->besoin_id, 'La formation est rattachée à la demande de sa ligne de plan.');
        $this->assertSame(StatutBesoin::Organisee, BesoinFormation::findOrFail($b1)->statut);
        $this->assertSame(StatutBesoin::Acceptee, BesoinFormation::findOrFail($b2)->statut, 'Sans formation : la demande redevient « acceptée », à organiser.');
        $this->assertSame(1, DB::table('sessions_formation')->where('action_id', $a)->count(), 'Une formation = une session.');
        $groupe2 = ActionFormation::where('reference', 'ACT-2026-0901-G2')->firstOrFail();
        $this->assertSame('ACT-2026-0901-S02', $groupe2->session()->first()->code);
        $this->assertSame(StatutAction::Planifiee, $groupe2->statut, 'Le statut suit celui de la session.');
        $this->assertSame(StatutAction::Realisee, ActionFormation::findOrFail($a)->statut);
        $this->assertDatabaseMissing('permissions', ['code' => 'plans.voir']);
        $this->assertDatabaseMissing('parametres', ['cle' => 'plans.interdire_auto_validation']);
        $this->assertDatabaseHas('plan_actions', ['code' => 'PA2026-001']); // données du plan conservées
        $this->assertDatabaseHas('audit_logs', ['action' => 'migration.formations_sans_plan']);
    }

    public function test_la_migration_1_4_0_simplifie_sans_rien_supprimer(): void
    {
        $chef = $this->utilisateur('chef_service');
        $id = DB::table('besoins_formation')->insertGetId(['reference' => 'BES-2026-0950', 'annee' => 2026, 'service_id' => $this->service1->id,
            'intitule' => 'En examen', 'statut' => 'en_examen', 'cout_previsionnel' => 1000, 'auteur_id' => $chef->id, 'created_at' => now(), 'updated_at' => now()]);
        $perm = DB::table('permissions')->insertGetId(['code' => 'finances.voir', 'module' => 'Finances', 'libelle' => 'X', 'sensible' => true, 'created_at' => now(), 'updated_at' => now()]);
        $roleFin = DB::table('roles')->insertGetId(['code' => 'gestionnaire_financier', 'nom' => 'Gestionnaire financier', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['role_id' => $roleFin, 'permission_id' => $perm]);
        DB::table('parametres')->insert(['cle' => 'besoins.statut_en_examen_actif', 'valeur' => '1', 'type' => 'bool', 'groupe' => 'besoins', 'libelle' => 'X', 'created_at' => now(), 'updated_at' => now()]);

        (require database_path('migrations/2026_10_22_001000_demandes_simplifiees_sans_finances.php'))->up();

        $this->assertSame('soumise', DB::table('besoins_formation')->where('id', $id)->value('statut'));
        $this->assertEquals(1000, DB::table('besoins_formation')->where('id', $id)->value('cout_previsionnel'), 'Les montants saisis restent en base.');
        $this->assertDatabaseMissing('permissions', ['code' => 'finances.voir']);
        $this->assertDatabaseMissing('roles', ['code' => 'gestionnaire_financier']);
        $this->assertDatabaseMissing('parametres', ['cle' => 'besoins.statut_en_examen_actif']);
        $this->assertTrue(Schema::hasTable('depenses'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'migration.demandes_simplifiees']);
    }

    public function test_la_migration_1_5_0_remet_les_formations_en_cours_a_programmees_sans_rien_supprimer(): void
    {
        $rh = $this->utilisateur('responsable_drh');
        $a = DB::table('actions_formation')->insertGetId(['reference' => 'ACT-2026-0960', 'annee' => 2026, 'intitule' => 'En cours', 'type' => 'interne', 'statut' => 'en_cours',
            'cree_par' => $rh->id, 'created_at' => now(), 'updated_at' => now()]);
        $sid = fn (string $c, string $st) => DB::table('sessions_formation')->insertGetId(['action_id' => $a, 'code' => 'ACT-2026-0960-'.$c, 'date_debut' => '2026-10-05',
            'date_fin' => '2026-10-05', 'places' => 5, 'statut' => $st, 'created_at' => now(), 'updated_at' => now()]);
        $enCours = $sid('S01', 'en_cours');
        $confirmee = $sid('S02', 'confirmee');
        $terminee = $sid('S03', 'terminee');
        $perm = DB::table('permissions')->insertGetId(['code' => 'presences.saisir', 'module' => 'Présences', 'libelle' => 'X', 'sensible' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['role_id' => Role::where('code', 'chef_service')->value('id'), 'permission_id' => $perm]);
        DB::table('permissions')->insert(['code' => 'evaluations.saisir', 'module' => 'Évaluations', 'libelle' => 'X', 'sensible' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('parametres')->insert(['cle' => 'regles.seuil_achevement', 'valeur' => '80', 'type' => 'int', 'groupe' => 'regles', 'libelle' => 'X', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'inscriptions.gerer')->update(['libelle' => 'Gérer les inscriptions']);

        (require database_path('migrations/2026_10_22_001100_formation_en_trois_etapes.php'))->up();

        $this->assertSame('planifiee', DB::table('sessions_formation')->where('id', $enCours)->value('statut'));
        $this->assertSame('planifiee', DB::table('sessions_formation')->where('id', $confirmee)->value('statut'));
        $this->assertSame('terminee', DB::table('sessions_formation')->where('id', $terminee)->value('statut'), 'Les formations terminées ne changent pas.');
        $this->assertSame('planifiee', DB::table('actions_formation')->where('id', $a)->value('statut'));
        $this->assertDatabaseMissing('permissions', ['code' => 'presences.saisir']);
        $this->assertDatabaseMissing('permissions', ['code' => 'evaluations.saisir']);
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $perm]);
        $this->assertDatabaseMissing('parametres', ['cle' => 'regles.seuil_achevement']);
        $this->assertSame('Gérer les participants des formations', DB::table('permissions')->where('code', 'inscriptions.gerer')->value('libelle'));
        $this->assertTrue(Schema::hasTable('presences') && Schema::hasTable('evaluations_satisfaction'), 'Les données de présence et d\'évaluation restent en base.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'migration.formation_trois_etapes']);
    }

    public function test_creation_d_un_compte_rh_et_profil_conserve_pour_un_compte_ancien(): void
    {
        $rh = $this->compteRh();
        $this->actingAs($rh)->get(route('admin.utilisateurs.create'))->assertOk()->assertSee('Responsable de structure')->assertSee('Gère tout')
            ->assertDontSee('Auditeur')->assertDontSee('Autorité de validation');
        $this->actingAs($rh)->post(route('admin.utilisateurs.store'), ['name' => 'Deuxième RH', 'email' => 'rh2@exemple.invalid', 'profil' => 'rh'])->assertRedirect();
        $u = User::where('email', 'rh2@exemple.invalid')->firstOrFail();
        $this->assertEqualsCanonicalizing(['responsable_drh', 'admin_technique'], $u->roles->pluck('code')->all());
        $this->assertSame('rh', Profils::de($u));
        $this->actingAs($rh)->post(route('admin.utilisateurs.store'), ['name' => 'X', 'email' => 'x@exemple.invalid', 'profil' => 'auditeur'])->assertSessionHasErrors('profil');

        // Compte antérieur avec un autre rôle : « Garder les droits actuels » ne change rien
        $ancien = $this->utilisateur('auditeur');
        $this->actingAs($rh)->get(route('admin.utilisateurs.edit', $ancien))->assertSee('Garder les droits actuels');
        $this->actingAs($rh)->put(route('admin.utilisateurs.update', $ancien), ['name' => 'Renommé', 'email' => $ancien->email, 'profil' => 'conserver'])->assertSessionHas('succes');
        $this->assertSame(['auditeur'], $ancien->fresh()->roles->pluck('code')->all());
        $this->actingAs($rh)->get(route('admin.roles.index'))->assertSee('Auditeur / contrôleur : 1');
        $this->actingAs($rh)->put(route('admin.utilisateurs.update', $u), ['name' => $u->name, 'email' => $u->email, 'profil' => 'conserver'])->assertSessionHasErrors('profil');
    }
}
