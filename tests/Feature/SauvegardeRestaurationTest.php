<?php

namespace Tests\Feature;

use App\Exceptions\RegleMetierException;
use App\Services\SauvegardeService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Sauvegarde puis restauration réelles (mysqldump / mysql) dans une base distincte.
 * N'utilise pas de transaction : les données doivent être validées pour être visibles de mysqldump.
 */
class SauvegardeRestaurationTest extends BaseTestCase
{
    private const BASE_RESTAURATION = 'inps_formation_restore_test';

    protected function setUp(): void
    {
        parent::setUp();
        if (! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
            $this->fail('Base de test requise.');
        }
        exec('command -v '.escapeshellarg(config('inps.sauvegarde.mysqldump')), $o, $code);
        if ($code !== 0) {
            $this->markTestSkipped('mysqldump indisponible dans cet environnement.');
        }
        config(['inps.sauvegarde.repertoire' => storage_path('framework/testing/sauvegardes'), 'inps.sauvegarde.mot_de_passe' => 'Phrase-De-Test-Chiffrement']);
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::statement('DROP DATABASE IF EXISTS `'.self::BASE_RESTAURATION.'`');
        File::deleteDirectory(storage_path('framework/testing/sauvegardes'));
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]); // état propre pour les autres tests
        parent::tearDown();
    }

    public function test_sauvegarde_chiffree_puis_restauration_verifiee(): void
    {
        DB::table('directions')->insert(['code' => 'SAV-1', 'nom' => 'Direction sauvegardée', 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('prive')->put('documents/test-sauvegarde.txt', 'contenu-du-document');
        $svc = app(SauvegardeService::class);

        $archive = $svc->sauvegarder();
        $this->assertFileExists($archive);
        $zip = new \ZipArchive;
        $zip->open($archive);
        $this->assertSame(\ZipArchive::EM_AES_256, $zip->statName('base.sql')['encryption_method'], 'L\'archive doit être chiffrée.');
        $zip->close();

        $r = $svc->restaurer($archive, self::BASE_RESTAURATION, false);
        $this->assertSame(self::BASE_RESTAURATION, $r['base']);
        $nb = DB::selectOne('SELECT COUNT(*) n FROM `'.self::BASE_RESTAURATION."`.directions WHERE code = 'SAV-1'")->n;
        $this->assertSame(1, (int) $nb);
        $perm = DB::selectOne('SELECT COUNT(*) n FROM `'.self::BASE_RESTAURATION.'`.permissions')->n;
        $this->assertSame(DB::table('permissions')->count(), (int) $perm);
        $this->assertStringContainsString('test-sauvegarde.txt', implode(',', array_keys(json_decode(
            (function () use ($archive) {
                $z = new \ZipArchive;
                $z->open($archive);
                $z->setPassword('Phrase-De-Test-Chiffrement');
                $m = $z->getFromName('manifeste.json');
                $z->close();

                return $m;
            })(), true)['fichiers'])));
        Storage::disk('prive')->delete('documents/test-sauvegarde.txt');
    }

    public function test_mauvais_mot_de_passe_ou_archive_alteree_refusee(): void
    {
        $archive = app(SauvegardeService::class)->sauvegarder();
        config(['inps.sauvegarde.mot_de_passe' => 'Mauvaise-Phrase']);
        $this->expectException(RegleMetierException::class);
        app(SauvegardeService::class)->restaurer($archive, self::BASE_RESTAURATION, false);
    }
}
