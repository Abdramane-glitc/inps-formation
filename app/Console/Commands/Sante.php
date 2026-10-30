<?php

namespace App\Console\Commands;

use App\Support\ProtectionAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Contrôle de santé de l'application (à intégrer à la supervision). Code retour ≠ 0 si anomalie bloquante. */
class Sante extends Command
{
    protected $signature = 'inps:sante';

    protected $description = 'Vérifier l\'état de l\'application (base, stockage, sécurité, sauvegardes, planificateur)';

    public function handle(): int
    {
        $bloquant = false;
        $ok = fn (string $m) => $this->line("  <fg=green>OK</>      $m");
        $ko = function (string $m) use (&$bloquant) {
            $bloquant = true;
            $this->line("  <fg=red>ÉCHEC</>   $m");
        };
        $warn = fn (string $m) => $this->line("  <fg=yellow>ATTENTION</> $m");
        $prod = app()->environment('production');

        try {
            DB::select('SELECT 1');
            $ok('Connexion à la base ('.config('database.default').')');
            $migrations = DB::table('migrations')->count();
            $fichiers = count(glob(database_path('migrations/*.php')));
            $migrations >= $fichiers ? $ok("Migrations appliquées ($migrations)") : $ko('Migrations en attente ('.($fichiers - $migrations).')');
            ProtectionAudit::estActive() ? $ok('Protection du journal d\'audit (déclencheurs)') : $warn('Déclencheurs du journal d\'audit absents (database/sql/protection_audit.sql)');
            DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->join('users', 'users.id', '=', 'role_user.user_id')
                ->where('roles.code', 'admin_technique')->where('users.actif', true)->exists() ? $ok('Au moins un administrateur technique actif') : $warn('Aucun administrateur technique actif (php artisan inps:creer-admin)');
        } catch (Throwable $e) {
            $ko('Base de données inaccessible : '.$e->getMessage());
        }
        config('app.key') ? $ok('Clé d\'application définie') : $ko('APP_KEY absente');
        is_writable(storage_path('app/private')) ? $ok('Stockage privé accessible en écriture') : $ko('storage/app/private non accessible en écriture');
        if ($prod) {
            config('app.debug') ? $ko('APP_DEBUG=true en production') : $ok('APP_DEBUG=false');
            config('inps.forcer_https') ? $ok('HTTPS forcé') : $ko('FORCE_HTTPS désactivé en production');
            config('session.secure') ? $ok('Cookies de session sécurisés') : $ko('SESSION_SECURE_COOKIE doit valoir true');
        }
        config('session.encrypt') ? $ok('Sessions chiffrées') : $warn('SESSION_ENCRYPT=false');
        in_array(config('mail.default'), ['log', 'array']) ? $warn('Aucun serveur SMTP configuré (MAIL_MAILER='.config('mail.default').') : seules les notifications internes sont actives') : $ok('Transport e-mail : '.config('mail.default'));
        $s = cache('inps.derniere_sauvegarde');
        $s && now()->diffInHours($s) < 30 ? $ok("Dernière sauvegarde : $s") : $warn('Aucune sauvegarde depuis plus de 30 h (ou jamais)');
        config('inps.sauvegarde.mot_de_passe') ? $ok('Sauvegardes chiffrées (BACKUP_PASSWORD)') : $warn('BACKUP_PASSWORD non défini : sauvegardes non chiffrées');
        $h = cache('inps.planificateur');
        $h && now()->diffInMinutes($h) < 1500 ? $ok("Planificateur actif (dernier passage : $h)") : $warn('Planificateur (schedule:run) non détecté depuis 25 h');

        return $bloquant ? self::FAILURE : self::SUCCESS;
    }
}
