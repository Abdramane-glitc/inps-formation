<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\BesoinFormation;
use App\Notifications\NotificationMetier;
use App\Services\Audit;
use App\Services\BesoinService;
use App\Support\ProtectionAudit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AuditSecuriteTest extends TestCase
{
    public function test_le_journal_enregistre_acteur_action_objet_et_ne_contient_pas_de_secret(): void
    {
        $chef = $this->utilisateur('chef_service');
        $this->actingAs($chef);
        $b = app(BesoinService::class)->creer(['annee' => 2026, 'service_id' => $this->service1->id, 'intitule' => 'X'], $chef);
        $l = AuditLog::where('action', 'besoin.creation')->firstOrFail();
        $this->assertSame($chef->id, $l->user_id);
        $this->assertSame('besoin', $l->objet_type);
        $this->assertSame($b->id, (int) $l->objet_id);
        $this->assertNotNull($l->created_at);
        $l2 = Audit::enregistrer('test.secret', null, 'x', ['password' => 'secret123', 'token' => 'abc', 'motif_absence' => 'Maladie', 'utile' => 1, 'imbrique' => ['password' => 'p']]);
        $this->assertSame(['utile' => 1, 'imbrique' => []], $l2->contexte);
    }

    public function test_le_journal_n_est_pas_modifiable_par_l_application(): void
    {
        $l = Audit::enregistrer('test.immuable');
        $this->expectException(\LogicException::class);
        $l->update(['action' => 'falsifie']);
    }

    public function test_le_journal_n_est_pas_supprimable_par_l_application(): void
    {
        $l = Audit::enregistrer('test.immuable');
        $this->expectException(\LogicException::class);
        $l->delete();
    }

    public function test_protection_en_base_par_declencheurs_mysql(): void
    {
        if (! ProtectionAudit::estActive()) {
            ProtectionAudit::installer();
        }
        if (! ProtectionAudit::estActive()) {
            $this->markTestSkipped('Déclencheurs non installables avec ce compte MySQL (voir database/sql/protection_audit.sql).');
        }
        $l = Audit::enregistrer('test.base');
        try {
            DB::table('audit_logs')->where('id', $l->id)->update(['action' => 'falsifie']);
            $this->fail('La modification directe aurait dû être bloquée.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('non modifiable', $e->getMessage());
        }
        try {
            DB::table('audit_logs')->where('id', $l->id)->delete();
            $this->fail('La suppression directe aurait dû être bloquée.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('non supprimable', $e->getMessage());
        }
    }

    public function test_consultation_filtree_et_export_du_journal_selon_droits(): void
    {
        Audit::enregistrer('securite.test', null, 'Événement filtré');
        $aud = $this->utilisateur('auditeur');
        $this->actingAs($aud)->get('/administration/audit?action=securite.test')->assertOk()->assertSee('Événement filtré');
        // Lecture en clair : libellé, personne, catégorie, détails techniques repliés
        Audit::enregistrer('securite.connexion', $aud, 'Connexion réussie', [], $aud->id);
        Audit::enregistrer('besoin.creation', null, 'Demande hors catégorie');
        $this->actingAs($aud)->get('/administration/audit?categorie=connexions')->assertOk()->assertSee('Connexion')->assertSee($aud->name)
            ->assertDontSee('Demande hors catégorie')->assertSee('<details>', false);
        $this->actingAs($aud)->get('/administration/audit?q=filtré')->assertOk()->assertSee('Événement filtré');
        $this->actingAs($aud)->get('/administration/audit/export')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'audit.export', 'user_id' => $aud->id]);
        $this->actingAs($this->utilisateur('responsable_drh'))->get('/administration/audit')->assertForbidden();
    }

    public function test_echappement_xss(): void
    {
        $chef = $this->utilisateur('chef_service');
        $b = app(BesoinService::class)->creer(['annee' => 2026, 'service_id' => $this->service1->id, 'intitule' => '<script>alert(1)</script>'], $chef);
        $this->actingAs($chef)->get(route('besoins.show', $b))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_entetes_de_securite(): void
    {
        $r = $this->get('/connexion');
        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'same-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $r->headers->get('Content-Security-Policy'));
    }

    public function test_protection_csrf_active(): void
    {
        // Le middleware CSRF est neutralisé par Laravel en tests unitaires : on vérifie qu'il est bien enregistré dans le groupe web.
        $groupe = app(Kernel::class)->getMiddlewareGroups()['web'];
        $this->assertNotEmpty(array_filter($groupe, fn ($m) => preg_match('/Csrf|Forgery/', $m)), 'Middleware anti-CSRF absent du groupe web.');
        $this->get('/connexion')->assertSee('name="_token"', false);
    }

    public function test_affectation_massive_ignoree_et_detectee(): void
    {
        $chef = $this->utilisateur('chef_service');
        $ag = Agent::create(['matricule' => 'MASS1', 'nom' => 'MASSIF', 'prenom' => 'A', 'service_id' => $this->service1->id]);
        $this->actingAs($chef)->post('/besoins', $this->analyse() + ['intitule' => 'Massif', 'contexte' => 'X', 'agents' => [$ag->id], 'reference' => 'PIRATE', 'deleted_at' => now(), 'annee' => 1999]);
        $b = BesoinFormation::where('intitule', 'Massif')->firstOrFail();
        $this->assertNotSame('PIRATE', $b->reference);
        $this->assertNotSame(1999, $b->annee);
        $this->expectException(MassAssignmentException::class);
        new BesoinFormation(['statut' => 'acceptee']);
    }

    public function test_erreurs_sans_fuite_d_information_hors_debug(): void
    {
        config(['app.debug' => false]);
        $this->actingAs($this->utilisateur('responsable_drh'))->get('/agents/999999')->assertNotFound()->assertDontSee('SQLSTATE')->assertDontSee('vendor/laravel');
    }

    public function test_redirection_ouverte_impossible_via_notification(): void
    {
        $u = $this->utilisateur('agent');
        $u->notify(new NotificationMetier('test', 'T', 'M', 'https://site-malveillant.example/'));
        $n = $u->notifications()->first();
        $r = $this->actingAs($u)->from('/notifications')->post(route('notifications.lue', $n->id));
        $this->assertStringNotContainsString('site-malveillant', (string) $r->headers->get('Location'));
        // Adresse qui commence comme celle de l'application mais pointe ailleurs
        $u->notify(new NotificationMetier('test', 'T', 'M', url('/').'.site-malveillant.example/x'));
        $piege = $u->notifications()->get()->first(fn ($n) => str_contains($n->data['url'] ?? '', '/x'));
        $r = $this->actingAs($u)->from('/notifications')->post(route('notifications.lue', $piege->id));
        $this->assertStringNotContainsString('site-malveillant', (string) $r->headers->get('Location'));
        // Une adresse interne reste suivie
        $u->notify(new NotificationMetier('test', 'T', 'M', route('notifications.index')));
        $ok = $u->notifications()->get()->first(fn ($n) => ($n->data['url'] ?? '') === route('notifications.index'));
        $this->actingAs($u)->post(route('notifications.lue', $ok->id))->assertRedirect(route('notifications.index'));
        $autre = $this->utilisateur('agent');
        $this->actingAs($autre)->post(route('notifications.lue', $n->id))->assertNotFound();
    }

    public function test_sante_de_l_application(): void
    {
        $this->artisan('inps:sante')->assertSuccessful();
    }

    public function test_ecran_sauvegardes_simplifie_et_telechargement_controle(): void
    {
        $dir = storage_path('framework/testing/sauvegardes-ecran');
        config(['inps.sauvegarde.repertoire' => $dir, 'inps.sauvegarde.mot_de_passe' => null]);
        File::ensureDirectoryExists($dir);
        file_put_contents("$dir/inps-formation-20261022-120000.zip", 'contenu');
        touch("$dir/inps-formation-20261022-120000.zip");
        file_put_contents("$dir/autre.zip", 'x');
        $admin = $this->utilisateur('admin_technique');
        try {
            $this->actingAs($admin)->get(route('admin.systeme.index'))->assertOk()->assertSee('Faire une sauvegarde')->assertSee('À vérifier')
                ->assertSee('Sauvegardes protégées par un mot de passe')->assertSee('Informations techniques (pour l', false)
                ->assertSee('Sauvegardes disponibles (1)');
            $this->actingAs($admin)->get(route('admin.systeme.telecharger', 'inps-formation-20261022-120000.zip'))->assertOk()->assertDownload('inps-formation-20261022-120000.zip');
            $this->assertDatabaseHas('audit_logs', ['action' => 'systeme.sauvegarde_telechargement', 'user_id' => $admin->id]);
            $this->actingAs($admin)->get('/administration/systeme/sauvegardes/autre.zip')->assertNotFound();
            $this->actingAs($admin)->get('/administration/systeme/sauvegardes/inps-formation-20991231-000000.zip')->assertNotFound();
            $this->actingAs($admin)->get('/administration/systeme/sauvegardes/..%2F..%2F.env')->assertNotFound();
            $this->actingAs($this->utilisateur('chef_service'))->get(route('admin.systeme.telecharger', 'inps-formation-20261022-120000.zip'))->assertForbidden();
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
