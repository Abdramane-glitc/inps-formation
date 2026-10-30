<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SauvegardeService;
use Tests\TestCase;

/** 29/10/2026 : désactivation des comptes de démonstration et chemin de mysqldump sous Laragon. */
class ComptesTestTest extends TestCase
{
    public function test_desactivation_des_comptes_de_test_avec_garde_fou(): void
    {
        $demo = $this->utilisateur('responsable_drh', null, ['email' => 'demo.responsable_drh@exemple.invalid']);
        $agentDemo = $this->utilisateur('agent', null, ['email' => 'agent0001@exemple.invalid']);

        // Aucun administrateur réel : refusé, rien n'est modifié
        $this->artisan('inps:comptes-test', ['--desactiver' => true])->assertFailed();
        $this->assertTrue($demo->fresh()->actif);

        $reel = $this->utilisateur('admin_technique', null, ['email' => 'admin.reel@inps.example']);
        $this->artisan('inps:comptes-test')->expectsOutputToContain('2 compte(s) de test, dont 2 actif(s)')->assertSuccessful();
        $this->assertTrue($demo->fresh()->actif, 'Sans option : liste seulement.');

        $this->artisan('inps:comptes-test', ['--desactiver' => true])->expectsOutputToContain('2 compte(s) de test désactivé(s)')->assertSuccessful();
        $this->assertFalse($demo->fresh()->actif);
        $this->assertFalse($agentDemo->fresh()->actif);
        $this->assertTrue($reel->fresh()->actif);
        $this->assertNotNull(User::find($demo->id), 'Jamais supprimé.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'utilisateur.desactivation_comptes_test']);
        // Un compte désactivé ne peut plus se connecter
        $this->post(route('login.store'), ['email' => $demo->email, 'password' => 'Mot-de-passe-Test-2026!']);
        $this->assertGuest();
    }

    public function test_mysqldump_de_laragon_trouve_automatiquement(): void
    {
        $racine = sys_get_temp_dir().'/laragon-'.uniqid();
        foreach (['mysql-5.7.44-winx64', 'mysql-8.4.3-winx64'] as $v) {
            mkdir("$racine/bin/mysql/$v/bin", 0777, true);
            touch("$racine/bin/mysql/$v/bin/mysqldump.exe");
        }
        config(['inps.sauvegarde.mysqldump' => 'mysqldump']);
        $this->assertSame("$racine/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe", str_replace('\\', '/', SauvegardeService::programme('mysqldump', $racine)), 'La version la plus récente.');
        $this->assertSame('mysql', SauvegardeService::programme('mysql', $racine), 'Introuvable : le nom seul.');
        config(['inps.sauvegarde.mysqldump' => 'D:\\outils\\mysqldump.exe']);
        $this->assertSame('D:\\outils\\mysqldump.exe', SauvegardeService::programme('mysqldump', $racine), 'Le chemin de .env passe en premier.');
    }
}
