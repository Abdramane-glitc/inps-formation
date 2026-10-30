<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthentificationTest extends TestCase
{
    public function test_la_page_de_connexion_s_affiche_et_les_invites_sont_rediriges(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Connexion');
        $this->get('/tableau-de-bord')->assertRedirect('/connexion');
        $this->get('/agents')->assertRedirect('/connexion');
    }

    public function test_connexion_reussie_et_journalisee(): void
    {
        $u = $this->utilisateur('responsable_drh');
        $this->post('/connexion', ['email' => $u->email, 'password' => 'Mot-de-passe-Test-2026!'])->assertRedirect(route('accueil'));
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('audit_logs', ['action' => 'securite.connexion', 'user_id' => $u->id]);
        $this->assertNotNull($u->fresh()->derniere_connexion_le);
    }

    public function test_mot_de_passe_incorrect_refuse_et_journalise_sans_identifiant_en_clair(): void
    {
        $u = $this->utilisateur('agent');
        $this->post('/connexion', ['email' => $u->email, 'password' => 'mauvais'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $log = AuditLog::where('action', 'securite.connexion_echec')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString($u->email, json_encode($log->contexte));
        $this->assertStringNotContainsString('mauvais', json_encode($log->toArray()));
    }

    public function test_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $u = $this->utilisateur('agent');
        $u->actif = false;
        $u->save();
        $this->post('/connexion', ['email' => $u->email, 'password' => 'Mot-de-passe-Test-2026!']);
        $this->assertGuest();
    }

    public function test_compte_desactive_en_cours_de_session_est_deconnecte(): void
    {
        $u = $this->utilisateur('responsable_drh');
        $this->actingAs($u);
        $u->actif = false;
        $u->save();
        $this->get('/tableau-de-bord')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_limitation_des_tentatives_de_connexion(): void
    {
        $u = $this->utilisateur('agent');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => $u->email, 'password' => 'faux'.$i]);
        }
        $this->post('/connexion', ['email' => $u->email, 'password' => 'Mot-de-passe-Test-2026!'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_deconnexion(): void
    {
        $this->actingAs($this->utilisateur('agent'))->post('/deconnexion')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reinitialisation_par_lien_puis_nouveau_mot_de_passe(): void
    {
        config(['mail.default' => 'smtp']);
        Notification::fake();
        $u = $this->utilisateur('agent');
        $this->post('/mot-de-passe-oublie', ['email' => $u->email])->assertSessionHas('succes');
        $jeton = null;
        Notification::assertSentTo($u, ResetPassword::class, function ($n) use (&$jeton) {
            $jeton = $n->token;

            return true;
        });
        $this->post('/reinitialisation', ['token' => $jeton, 'email' => $u->email, 'password' => 'Nouveau-MdP-Solide-99!', 'password_confirmation' => 'Nouveau-MdP-Solide-99!'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Nouveau-MdP-Solide-99!', $u->fresh()->password));
        // Le jeton est à usage unique
        $this->post('/reinitialisation', ['token' => $jeton, 'email' => $u->email, 'password' => 'Encore-Autre-MdP-77!', 'password_confirmation' => 'Encore-Autre-MdP-77!'])
            ->assertSessionHasErrors('email');
    }

    public function test_demande_de_reinitialisation_ne_revele_pas_l_existence_du_compte(): void
    {
        config(['mail.default' => 'smtp']);
        Notification::fake();
        $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@exemple.invalid'])->assertSessionHas('succes');
        Notification::assertNothingSent();
    }

    public function test_sans_smtp_la_page_ne_propose_pas_d_envoi(): void
    {
        config(['mail.default' => 'log']);
        $this->get('/mot-de-passe-oublie')->assertOk()->assertSee('Demandez à l\'administrateur', false)->assertDontSee('Recevoir le lien');
    }

    public function test_politique_de_mot_de_passe_appliquee(): void
    {
        $u = $this->utilisateur('agent');
        $this->actingAs($u)->put('/mon-mot-de-passe', ['mot_de_passe_actuel' => 'Mot-de-passe-Test-2026!', 'password' => 'simple', 'password_confirmation' => 'simple'])
            ->assertSessionHasErrors('password');
        $this->actingAs($u)->put('/mon-mot-de-passe', ['mot_de_passe_actuel' => 'Mot-de-passe-Test-2026!', 'password' => 'Tres-Bon-MotDePasse-42', 'password_confirmation' => 'Tres-Bon-MotDePasse-42'])
            ->assertRedirect(route('accueil'));
        $this->assertTrue(Hash::check('Tres-Bon-MotDePasse-42', $u->fresh()->password));
    }

    public function test_changement_obligatoire_du_mot_de_passe_temporaire(): void
    {
        $u = $this->utilisateur('responsable_drh');
        $u->doit_changer_mot_de_passe = true;
        $u->save();
        $this->actingAs($u)->get('/tableau-de-bord')->assertRedirect(route('mot-de-passe.edit'));
        $this->actingAs($u)->get('/mon-mot-de-passe')->assertOk();
    }

    public function test_aucun_compte_par_defaut_apres_installation(): void
    {
        $this->assertSame(0, User::count(), 'Le référentiel initial ne doit créer aucun compte.');
    }

    public function test_creation_securisee_du_premier_administrateur_en_console(): void
    {
        $this->artisan('inps:creer-admin', ['--email' => 'admin@exemple.invalid', '--nom' => 'Admin'])
            ->expectsQuestion('Mot de passe (saisie masquée)', 'faible')
            ->expectsQuestion('Confirmation du mot de passe', 'faible')
            ->assertFailed();
        $this->assertSame(0, User::count());
        $this->artisan('inps:creer-admin', ['--email' => 'admin@exemple.invalid', '--nom' => 'Admin'])
            ->expectsQuestion('Mot de passe (saisie masquée)', 'Admin-Solide-2026!')
            ->expectsQuestion('Confirmation du mot de passe', 'Admin-Solide-2026!')
            ->assertSuccessful();
        $u = User::where('email', 'admin@exemple.invalid')->firstOrFail();
        $this->assertTrue($u->aPermission('utilisateurs.gerer'));
        $this->assertFalse($u->aPermission('actions.gerer'), 'L\'administrateur technique n\'a pas d\'accès métier par défaut.');
    }
}
