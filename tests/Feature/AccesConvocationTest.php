<?php

namespace Tests\Feature;

use App\Enums\StatutInscription;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\EnvoiEmail;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\User;
use App\Services\AccesAgentService;
use App\Services\ActionService;
use App\Services\BesoinService;
use App\Services\ConvocationService;
use App\Services\InscriptionService;
use App\Support\Profils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 2.3.0 : accès des agents (identifiant = matricule) et convocation des participants.
 */
class AccesConvocationTest extends TestCase
{
    private function compteRh(): User
    {
        $u = $this->utilisateur('responsable_drh');
        $u->roles()->attach(Role::where('code', 'admin_technique')->value('id'));

        return $u->fresh();
    }

    /** 2.4.0 : code d'activation à 6 chiffres affiché à la RH ; l'agent active son compte et choisit son mot de passe. */
    public function test_acces_des_agents_par_code(): void
    {
        $rh = $this->compteRh();
        $a1 = Agent::create(['matricule' => 'MAT-001', 'nom' => 'KONE', 'prenom' => 'Awa', 'service_id' => $this->service1->id, 'email_pro' => 'awa.kone@exemple.invalid']);
        $a2 = Agent::create(['matricule' => 'MAT-002', 'nom' => 'SIDIBE', 'prenom' => 'Ali', 'service_id' => $this->service1->id]);
        $this->actingAs($this->utilisateur('chef_service'))->get(route('agents.acces'))->assertForbidden();
        $this->actingAs($rh)->get(route('agents.acces'))->assertOk()->assertSee('KONE Awa')->assertSee('Pas d&#039;accès', false);

        // « Donner un code » : comptes créés, codes de 6 chiffres affichés ; recliquer ne change rien
        $this->actingAs($rh)->post(route('agents.acces.generer'), ['agents' => [$a1->id, $a2->id]])->assertSessionHas('succes', fn ($m) => str_contains($m, '2 code(s)'));
        $u2 = $a2->fresh()->user;
        $this->assertTrue(Profils::estAgent($u2));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $u2->code_activation);
        $this->assertSame('awa.kone@exemple.invalid', $a1->fresh()->user->email);
        $this->assertNull($u2->email);
        $code = $u2->code_activation;
        $this->actingAs($rh)->post(route('agents.acces.generer'), ['agents' => [$a1->id, $a2->id]])->assertSessionHas('succes', fn ($m) => str_contains($m, 'Rien à faire'));
        $this->assertSame($code, $a2->fresh()->user->code_activation, 'Un double clic ne change pas le code.');
        $this->actingAs($rh)->get(route('agents.acces'))->assertSee(substr($code, 0, 3).' '.substr($code, 3));
        $this->actingAs($rh)->get(route('agents.acces.liste'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseMissing('audit_logs', ['contexte' => json_encode(['code' => $code])]);
        $this->assertStringNotContainsString($code, json_encode(AuditLog::pluck('contexte')));
        // Le code est chiffré en base
        $this->assertStringNotContainsString($code, (string) DB::table('users')->where('id', $u2->id)->value('code_activation'));
        // Les comptes d'agents restent hors de la liste des utilisateurs
        $this->actingAs($rh)->get(route('admin.utilisateurs.index'))->assertDontSee('SIDIBE Ali');
        $this->actingAs($rh)->get(route('admin.utilisateurs.index', ['profil' => 'agent']))->assertSee('SIDIBE Ali')->assertSee('matricule MAT-002');
        auth()->logout();

        // Connexion tentée avant l'activation : orientation vers « Activer mon compte »
        $this->post(route('login.store'), ['email' => 'MAT-002', 'password' => 'Nimporte-quoi-1!'])->assertRedirect(route('activation'))
            ->assertSessionHas('avertissement', fn ($m) => str_contains($m, 'pas encore activé'));
        // Page de connexion → « Activer mon compte »
        $this->get(route('login'))->assertSee('Activer mon compte');
        $this->get(route('activation'))->assertOk()->assertSee('Code d&#039;activation', false);
        // Mauvais code : refusé, message neutre, le mot de passe n'est pas renvoyé
        $faux = $code === '135790' ? '246802' : '135790';
        $this->post(route('activation.store'), ['matricule' => 'mat-002', 'code' => $faux, 'password' => 'Mon-Mot-2026!', 'password_confirmation' => 'Mon-Mot-2026!'])
            ->assertSessionHasErrors(['code' => 'Matricule ou code incorrect, ou code expiré. Demandez un nouveau code à la Direction des ressources humaines.'])
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
        // Bon code (avec espace) : compte activé, agent connecté dans son espace
        $this->post(route('activation.store'), ['matricule' => 'mat-002', 'code' => substr($code, 0, 3).' '.substr($code, 3), 'password' => 'Mon-Mot-2026!', 'password_confirmation' => 'Mon-Mot-2026!'])
            ->assertRedirect(route('espace.index'));
        $this->assertAuthenticatedAs($u2->fresh());
        $u2 = $u2->fresh();
        $this->assertNull($u2->code_activation);
        $this->assertNotNull($u2->active_le);
        $this->get(route('espace.index'))->assertOk()->assertSee('Mes formations');
        $this->get(route('formations.index'))->assertForbidden();
        $this->post(route('logout'));

        // Le code ne sert qu'une fois ; connexion ensuite avec matricule et mot de passe
        $this->post(route('activation.store'), ['matricule' => 'MAT-002', 'code' => $code, 'password' => 'Autre-Mot-2026!', 'password_confirmation' => 'Autre-Mot-2026!'])->assertSessionHasErrors('code');
        $this->post(route('login.store'), ['email' => 'mat-002', 'password' => 'Mon-Mot-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($u2);
        auth()->logout();

        // Agent désactivé : son accès est désactivé
        $this->actingAs($rh)->post(route('agents.activation', $a2), ['actif' => 0, 'motif' => 'Départ'])->assertSessionHas('succes');
        $this->assertFalse($a2->fresh()->user->actif);
        auth()->logout();
        $this->post(route('login.store'), ['email' => 'MAT-002', 'password' => 'Mon-Mot-2026!'])->assertSessionHasErrors('email');
    }

    /** 2.4.0 : 5 essais au plus par code ; expiration ; nouveau code ; commande de dépannage. */
    public function test_securite_des_codes(): void
    {
        $rh = $this->compteRh();
        $ag = Agent::create(['matricule' => 'SEC-1', 'nom' => 'SURE', 'prenom' => 'Test', 'service_id' => $this->service1->id]);
        app(AccesAgentService::class)->genererCodes([$ag->id], $rh);
        $code = $ag->fresh()->user->code_activation;
        $faux = $code === '135790' ? '246802' : '135790';
        $donnees = fn ($c) => ['matricule' => 'SEC-1', 'code' => $c, 'password' => 'Mon-Mot-2026!', 'password_confirmation' => 'Mon-Mot-2026!'];
        for ($n = 0; $n < AccesAgentService::ESSAIS_MAX; $n++) {
            $this->post(route('activation.store'), $donnees($faux))->assertSessionHasErrors('code');
        }
        // Après 5 essais erronés, même le bon code est refusé
        $this->post(route('activation.store'), $donnees($code))->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'securite.code_activation_bloque']);

        // La RH donne un nouveau code : il fonctionne
        $this->actingAs($rh)->post(route('agents.acces.nouveau-code', $ag))->assertSessionHas('succes', fn ($m) => str_contains($m, 'Nouveau code pour SURE Test'));
        $nouveau = $ag->fresh()->user->code_activation;
        auth()->logout();
        // Expiré : refusé
        $this->travel(AccesAgentService::JOURS_VALIDITE + 1)->days();
        $this->post(route('activation.store'), $donnees($nouveau))->assertSessionHasErrors('code');
        $this->travelBack();
        $this->post(route('activation.store'), $donnees($nouveau))->assertRedirect(route('espace.index'));

        // Commande de dépannage
        auth()->logout();
        $this->artisan('inps:code-agent', ['matricule' => 'INCONNU'])->expectsOutputToContain('Aucun agent')->assertFailed();
        $this->artisan('inps:code-agent', ['matricule' => 'sec-1'])->expectsOutputToContain('Code d\'activation : ')->assertSuccessful();
        $this->assertTrue($ag->fresh()->user->aCodeValide());
    }

    public function test_convocation_des_participants(): void
    {
        $rh = $this->compteRh();
        $chef1 = $this->utilisateur('chef_service');
        $chef2 = $this->utilisateur('chef_service', $this->service2);
        $a1 = Agent::create(['matricule' => 'C1', 'nom' => 'DIARRA', 'prenom' => 'Awa', 'service_id' => $this->service1->id]);
        $a2 = Agent::create(['matricule' => 'C2', 'nom' => 'TRAORE', 'prenom' => 'Ali', 'service_id' => $this->service2->id, 'email_pro' => 'ali.traore@exemple.invalid']);
        $a3 = Agent::create(['matricule' => 'C3', 'nom' => 'KEITA', 'prenom' => 'Sira', 'service_id' => $this->service1->id]);
        app(AccesAgentService::class)->genererCodes([$a1->id], $rh);
        $s = $this->nouvelleFormation($rh, ['date_debut' => today()->addDays(10)->toDateString(), 'date_fin' => today()->addDays(11)->toDateString(), 'lieu' => 'Salle A']);
        app(InscriptionService::class)->inscrire($s, [$a1->id, $a2->id], $rh);
        $a = $s->action;

        // Prochaine étape : convoquer ; le responsable de structure ne convoque pas
        $this->actingAs($rh)->get(route('formations.show', $a))->assertSee('Convoquer les participants');
        $this->actingAs($chef1)->post(route('formations.convoquer', $a))->assertForbidden();

        // Messagerie active (serveur simulé) : l'agent sans accès mais avec une adresse reçoit sa convocation par e-mail
        Parametre::where('cle', 'notifications.email_actif')->update(['valeur' => '1']);
        Cache::forget('parametres.tous');
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->actingAs($rh)->post(route('formations.convoquer', $a))->assertSessionHas('succes', fn ($m) => str_contains($m, 'Participants convoqués : 2 participant(s)'));
        $this->assertTrue($s->fresh()->participantsActifs()->get()->every(fn ($i) => $i->convoque_le !== null && $i->statut === StatutInscription::Convoque));
        $this->assertSame(1, $chef1->notifications()->where('data->categorie', 'convocation')->count());
        $this->assertStringContainsString('DIARRA Awa', $chef1->notifications()->first()->data['message']);
        $this->assertSame(1, $chef2->notifications()->where('data->categorie', 'convocation')->count());
        $this->assertSame(1, $a1->fresh()->user->notifications()->where('data->categorie', 'convocation')->count());
        $this->assertSame(1, EnvoiEmail::where('statut', 'envoye')->whereNull('user_id')->count(), 'E-mail avec la convocation jointe à l\'agent sans accès.');
        $this->assertSame(2, EnvoiEmail::where('statut', 'envoye')->whereNotNull('user_id')->count(), 'Les responsables de structure reçoivent aussi l\'e-mail de notification.');
        $this->actingAs($rh)->get(route('formations.convocations', $a))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($rh)->post(route('formations.convoquer', $a), ['nouveaux' => 1])->assertSessionHas('erreur', fn ($m) => str_contains($m, 'déjà convoqués'));

        // Mon espace : l'agent voit la formation à venir et télécharge sa convocation, pas celle d'un autre
        $u1 = $a1->fresh()->user;
        $ins1 = $s->inscriptions()->where('agent_id', $a1->id)->first();
        $ins2 = $s->inscriptions()->where('agent_id', $a2->id)->first();
        $this->actingAs($u1)->get(route('espace.index'))->assertOk()->assertSee('À venir')->assertSee('Télécharger ma convocation')->assertSee('Salle A');
        $this->actingAs($u1)->get(route('espace.convocation', $ins1))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($u1)->get(route('espace.convocation', $ins2))->assertForbidden();

        // Un participant ajouté ensuite : « Convoquer les nouveaux » ne convoque que lui
        app(InscriptionService::class)->inscrire($s->fresh(), [$a3->id], $rh);
        $etat = app(ConvocationService::class)->etat($s->fresh());
        $this->assertSame(1, $etat['non_convoques']);
        $this->actingAs($rh)->get(route('formations.show', $a))->assertSee('Convoquer les nouveaux');
        $this->actingAs($rh)->post(route('formations.convoquer', $a), ['nouveaux' => 1])->assertSessionHas('succes', fn ($m) => str_contains($m, ': 1 participant(s)'));
        $this->assertSame(2, $chef1->notifications()->where('data->categorie', 'convocation')->count());

        // Changement de lieu après la convocation : convocation rectificative pour tous
        app(ActionService::class)->modifierFormation($a->fresh(), ['intitule' => $a->intitule, 'type' => 'interne', 'lieu' => 'Salle B'], $rh, 'Salle indisponible');
        $this->assertTrue(app(ConvocationService::class)->etat($s->fresh())['a_reconvoquer']);
        $this->actingAs($rh)->get(route('formations.show', $a))->assertSee('envoyez une convocation rectificative');
        $this->actingAs($rh)->post(route('formations.convoquer', $a), ['nouveaux' => 1])->assertSessionHas('succes', fn ($m) => str_contains($m, 'Convocation rectificative envoyée : 3 participant(s)'));
        $this->assertFalse(app(ConvocationService::class)->etat($s->fresh())['a_reconvoquer']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'formation.convocation', 'objet_id' => $a->id]);

        // Formation terminée : plus de convocation
        app(ActionService::class)->terminer($s->fresh(), [$ins1->id], $rh);
        $this->actingAs($rh)->post(route('formations.convoquer', $a))->assertSessionHas('erreur');
    }

    /**
     * 28/10/2026 : à l'acceptation d'une demande, les agents sont convoqués aussitôt (case cochée par défaut) ;
     * chaque agent qui a une adresse reçoit sa convocation en PDF par e-mail, même s'il a un compte ;
     * un participant ajouté ensuite est convoqué automatiquement.
     */
    public function test_convocation_automatique_a_l_acceptation(): void
    {
        $rh = $this->compteRh();
        $chef = $this->utilisateur('chef_service');
        $avecCompte = Agent::create(['matricule' => 'K1', 'nom' => 'COULIBALY', 'prenom' => 'Fanta', 'service_id' => $this->service1->id, 'email_pro' => 'fanta.coulibaly@exemple.invalid']);
        $sansAdresse = Agent::create(['matricule' => 'K2', 'nom' => 'SANGARE', 'prenom' => 'Moussa', 'service_id' => $this->service1->id]);
        $ajoute = Agent::create(['matricule' => 'K3', 'nom' => 'DEMBELE', 'prenom' => 'Oumou', 'service_id' => $this->service1->id, 'email_pro' => 'oumou.dembele@exemple.invalid']);
        app(AccesAgentService::class)->genererCodes([$avecCompte->id], $rh);
        $compte = $avecCompte->fresh()->user;
        $this->assertSame('fanta.coulibaly@exemple.invalid', $compte->email);

        Parametre::where('cle', 'notifications.email_actif')->update(['valeur' => '1']);
        Cache::forget('parametres.tous');
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $analyse = ['diag_savoir' => 'non', 'diag_moyens' => 'oui', 'diag_temps' => 'oui', 'gravite' => 'reguliere', 'situation_actuelle' => 'A', 'objectif_vise' => 'B',
            'solutions_essayees' => ['aucune'], 'justification_formation' => 'C', 'resultat_attendu' => 'D'];
        $b = app(BesoinService::class)->creer(['intitule' => 'Accueil des assurés', 'contexte' => 'Réclamations'] + $analyse, $chef, [$avecCompte->id, $sansAdresse->id]);

        $this->actingAs($rh)->post(route('besoins.accepter', $b), ['date_debut' => today()->addDays(15)->toDateString(), 'date_fin' => today()->addDays(16)->toDateString(), 'lieu' => 'Salle C'])
            ->assertSessionHas('succes', fn ($m) => str_contains($m, 'Participants convoqués : 2 participant(s)') && str_contains($m, '1 e-mail(s) envoyé(s)')
                && str_contains($m, '1 participant(s) sans adresse e-mail'));
        $s = $b->fresh()->formation->session;
        $this->assertNotNull($s->convoquee_le);
        $this->assertTrue($s->participantsActifs()->get()->every(fn ($i) => $i->statut === StatutInscription::Convoque));
        // L'agent qui a un compte : notification dans « Mon espace » et un seul e-mail, celui qui porte la convocation
        $this->assertSame(1, $compte->notifications()->where('data->categorie', 'convocation')->count());
        $this->assertSame(1, EnvoiEmail::where('user_id', $compte->id)->where('statut', 'envoye')->count());
        $ins = $s->inscriptions()->where('agent_id', $avecCompte->id)->first();
        $this->actingAs($compte)->get(route('espace.index'))->assertSee('Télécharger ma convocation')->assertSee('Reçue le');
        $this->actingAs($compte)->get(route('espace.convocation', $ins))->assertOk();

        // Participant ajouté après la convocation : convoqué aussitôt
        $this->actingAs($rh)->post(route('formations.participants', $s->action_id), ['agents' => [$ajoute->id]])
            ->assertSessionHas('succes', fn ($m) => str_contains($m, '1 participant(s) ajouté(s).') && str_contains($m, 'Participants convoqués : 1 participant(s)'));
        $this->assertNotNull($s->inscriptions()->where('agent_id', $ajoute->id)->value('convoque_le'));

        // Case décochée : la formation est programmée sans convocation
        $b2 = app(BesoinService::class)->creer(['intitule' => 'Archivage', 'contexte' => 'Retards'] + $analyse, $chef, [$sansAdresse->id]);
        $this->actingAs($rh)->post(route('besoins.accepter', $b2), ['date_debut' => today()->addDays(40)->toDateString(), 'date_fin' => today()->addDays(40)->toDateString(), 'convoquer' => '0'])
            ->assertSessionHas('succes', fn ($m) => ! str_contains($m, 'convoqués'));
        $this->assertNull($b2->fresh()->formation->session->convoquee_le);
    }

    /** Messagerie non configurée : la convocation est enregistrée et le message le dit, sans prétendre avoir envoyé d'e-mail. */
    public function test_convocation_sans_messagerie(): void
    {
        $rh = $this->compteRh();
        $a = Agent::create(['matricule' => 'M1', 'nom' => 'TOURE', 'prenom' => 'Ina', 'service_id' => $this->service1->id, 'email_pro' => 'ina.toure@exemple.invalid']);
        $s = $this->nouvelleFormation($rh, ['date_debut' => today()->addDays(10)->toDateString(), 'date_fin' => today()->addDays(10)->toDateString()]);
        app(InscriptionService::class)->inscrire($s, [$a->id], $rh);
        Parametre::where('cle', 'notifications.email_actif')->update(['valeur' => '1']);
        Cache::forget('parametres.tous');
        config(['mail.default' => 'log']);

        $this->actingAs($rh)->post(route('formations.convoquer', $s->action_id))
            ->assertSessionHas('succes', fn ($m) => str_contains($m, 'Aucun e-mail n\'est parti : la messagerie n\'est pas configurée'));
        $this->assertSame(0, EnvoiEmail::where('statut', 'envoye')->count());
    }
}
