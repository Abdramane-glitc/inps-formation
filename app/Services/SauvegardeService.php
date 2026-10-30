<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Support\ProtectionAudit;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Sauvegarde / restauration : base MySQL (mysqldump) + documents privés, dans une archive ZIP
 * chiffrée AES-256 si BACKUP_PASSWORD est défini, avec manifeste d'intégrité (SHA-256).
 * Les identifiants MySQL sont passés via un fichier d'options temporaire (jamais en ligne de commande).
 */
class SauvegardeService
{
    public function repertoire(): string
    {
        $dir = trim((string) config('inps.sauvegarde.repertoire')) ?: storage_path('app/sauvegardes');
        File::ensureDirectoryExists($dir, 0700);

        return $dir;
    }

    public function sauvegarder(?string $connexion = null): string
    {
        $cfg = config('database.connections.'.($connexion ?? config('database.default')));
        if (($cfg['driver'] ?? null) !== 'mysql') {
            throw new RegleMetierException('La sauvegarde automatisée nécessite MySQL.');
        }
        $tmp = storage_path('app/sauvegarde-tmp-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($tmp, 0700);
        try {
            $dump = $tmp.'/base.sql';
            $this->executer([self::programme('mysqldump'), '--defaults-extra-file='.$this->fichierOptions($cfg, $tmp),
                '--single-transaction', '--quick', '--routines', '--skip-triggers', '--default-character-set=utf8mb4',
                '--no-tablespaces', '--result-file='.$dump, $cfg['database']]);

            $nom = 'inps-formation-'.now()->format('Ymd-His').'.zip';
            $chemin = $this->repertoire().'/'.$nom;
            $zip = new ZipArchive;
            if ($zip->open($chemin, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RegleMetierException("Impossible de créer l'archive $chemin.");
            }
            $mdp = config('inps.sauvegarde.mot_de_passe');
            $manifeste = ['application' => 'inps-formation', 'date' => now()->toIso8601String(), 'base' => $cfg['database'], 'fichiers' => []];
            $ajouter = function (string $source, string $dans) use ($zip, $mdp, &$manifeste) {
                $zip->addFile($source, $dans);
                if ($mdp) {
                    $zip->setEncryptionName($dans, ZipArchive::EM_AES_256, $mdp);
                }
                $manifeste['fichiers'][$dans] = hash_file('sha256', $source);
            };
            $ajouter($dump, 'base.sql');
            $prive = storage_path('app/private');
            if (is_dir($prive)) {
                foreach (File::allFiles($prive) as $f) {
                    $ajouter($f->getPathname(), 'fichiers/'.str_replace('\\', '/', $f->getRelativePathname()));
                }
            }
            $zip->addFromString('manifeste.json', json_encode($manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($mdp) {
                $zip->setEncryptionName('manifeste.json', ZipArchive::EM_AES_256, $mdp);
            }
            $zip->close();
            @chmod($chemin, 0600);
            $this->appliquerRetention();
            Audit::enregistrer('systeme.sauvegarde', null, "Sauvegarde créée : $nom", ['chiffree' => (bool) $mdp, 'fichiers' => count($manifeste['fichiers'])]);

            return $chemin;
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    /** Restaure une archive dans la base indiquée (par défaut celle de la connexion). */
    public function restaurer(string $archive, ?string $baseCible = null, bool $avecFichiers = true, ?string $connexion = null): array
    {
        if (! is_file($archive)) {
            throw new RegleMetierException("Archive introuvable : $archive");
        }
        $cfg = config('database.connections.'.($connexion ?? config('database.default')));
        $base = $baseCible ?? $cfg['database'];
        $tmp = storage_path('app/restauration-tmp-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($tmp, 0700);
        try {
            $zip = new ZipArchive;
            if ($zip->open($archive) !== true) {
                throw new RegleMetierException('Archive illisible.');
            }
            if ($mdp = config('inps.sauvegarde.mot_de_passe')) {
                $zip->setPassword($mdp);
            }
            if (! $zip->extractTo($tmp)) {
                throw new RegleMetierException('Extraction impossible (mot de passe BACKUP_PASSWORD incorrect ?).');
            }
            $zip->close();
            $manifeste = json_decode((string) @file_get_contents($tmp.'/manifeste.json'), true);
            if (! $manifeste || ($manifeste['application'] ?? null) !== 'inps-formation') {
                throw new RegleMetierException('Manifeste absent ou invalide : archive non reconnue.');
            }
            foreach ($manifeste['fichiers'] as $f => $hash) {
                if (! is_file("$tmp/$f") || hash_file('sha256', "$tmp/$f") !== $hash) {
                    throw new RegleMetierException("Contrôle d'intégrité échoué pour « $f » : restauration interrompue.");
                }
            }
            $opt = $this->fichierOptions($cfg, $tmp);
            $this->executer([self::programme('mysql'), '--defaults-extra-file='.$opt, '-e', 'CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $base).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci']);
            $process = new Process([self::programme('mysql'), '--defaults-extra-file='.$opt, '--default-character-set=utf8mb4', $base], null, self::environnement());
            $process->setInput(fopen("$tmp/base.sql", 'r'));
            $process->setTimeout(3600)->mustRun();

            $nbFichiers = 0;
            if ($avecFichiers && is_dir("$tmp/fichiers")) {
                File::ensureDirectoryExists(storage_path('app/private'), 0750);
                File::copyDirectory("$tmp/fichiers", storage_path('app/private'));
                $nbFichiers = count(File::allFiles("$tmp/fichiers"));
            }
            $triggers = $base === $cfg['database'] ? ProtectionAudit::installer() : null;

            return ['base' => $base, 'fichiers' => $nbFichiers, 'date_sauvegarde' => $manifeste['date'], 'protection_audit' => $triggers];
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    public function liste(): array
    {
        return collect(File::glob($this->repertoire().'/inps-formation-*.zip'))->sortDesc()->values()
            ->map(fn ($f) => ['fichier' => basename($f), 'taille' => filesize($f), 'date' => filemtime($f)])->all();
    }

    private function appliquerRetention(): void
    {
        $garder = (int) config('inps.sauvegarde.retention', 14);
        $fichiers = collect(File::glob($this->repertoire().'/inps-formation-*.zip'))->sortDesc()->values();
        $fichiers->slice($garder)->each(fn ($f) => File::delete($f));
    }

    private function fichierOptions(array $cfg, string $dir): string
    {
        $f = $dir.'/my.cnf';
        $c = "[client]\nuser=\"{$cfg['username']}\"\npassword=\"".addcslashes((string) $cfg['password'], '"\\')."\"\n";
        $c .= ! empty($cfg['unix_socket']) ? "socket=\"{$cfg['unix_socket']}\"\n" : "host=\"{$cfg['host']}\"\nport={$cfg['port']}\n";
        file_put_contents($f, $c);
        @chmod($f, 0600);

        return $f;
    }

    /**
     * Chemin de mysqldump ou mysql (29/10/2026) : celui de .env (MYSQLDUMP_PATH, MYSQL_CLIENT_PATH) s'il est renseigné ;
     * sinon, sous Windows, celui de Laragon (C:\laragon\bin\mysql\…\bin), car l'application lancée hors du terminal
     * Laragon ne le trouve pas (« 'mysqldump' n'est pas reconnu… », constaté le 29/10/2026) ; sinon le nom seul (PATH).
     *
     * @param  'mysqldump'|'mysql'  $nom
     */
    public static function programme(string $nom, ?string $racineLaragon = null): string
    {
        $configure = (string) config('inps.sauvegarde.'.$nom);
        if ($configure !== '' && $configure !== $nom) {
            return $configure;
        }
        if ($racineLaragon === null && \DIRECTORY_SEPARATOR !== '\\') {
            return $nom;
        }
        $dossier = rtrim($racineLaragon ?? 'C:\\laragon', '\\/').'/bin/mysql/*/bin/';
        $trouves = array_merge(glob($dossier.$nom.'.exe') ?: [], glob($dossier.$nom) ?: []);
        natsort($trouves); // la version la plus récente en dernier

        return $trouves ? (string) end($trouves) : $nom;
    }

    /**
     * Variables d'environnement transmises à mysqldump et mysql (28/10/2026).
     * Sous Windows, un programme lancé sans SYSTEMROOT ne peut pas ouvrir de connexion réseau
     * (« Can't create TCP/IP socket (10106) ») : c'est le cas depuis la page Sauvegardes avec « php artisan serve »,
     * qui ne transmet qu'une partie des variables. On les fournit donc explicitement.
     */
    public static function environnement(): array
    {
        if (\DIRECTORY_SEPARATOR !== '\\') {
            return [];
        }
        $racine = getenv('SYSTEMROOT') ?: getenv('WINDIR') ?: ($_SERVER['SYSTEMROOT'] ?? $_SERVER['SystemRoot'] ?? 'C:\\Windows');

        return ['SYSTEMROOT' => $racine, 'WINDIR' => getenv('WINDIR') ?: $racine];
    }

    private function executer(array $cmd): void
    {
        $p = new Process($cmd, null, self::environnement());
        $p->setTimeout(3600);
        $p->run();
        if (! $p->isSuccessful()) {
            // 127 (Linux) ou 9009 (Windows) : le programme lui-même n'a pas été trouvé
            $introuvable = in_array($p->getExitCode(), [127, 9009], true) || preg_match('/not found|introuvable|n.est pas reconnu|cannot find|No such file/i', $p->getErrorOutput());
            throw new RegleMetierException('Commande '.basename($cmd[0]).' en échec : '.mb_substr(trim($p->getErrorOutput()), 0, 400)
                .($introuvable ? ' [introuvable]' : ''));
        }
    }
}
