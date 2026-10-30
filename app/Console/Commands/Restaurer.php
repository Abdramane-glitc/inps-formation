<?php

namespace App\Console\Commands;

use App\Services\SauvegardeService;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\text;

class Restaurer extends Command
{
    protected $signature = 'inps:restaurer {archive : chemin de l\'archive ZIP} {--base= : base cible (par défaut la base de l\'application)} {--sans-fichiers : ne pas restaurer les documents} {--force : ne pas demander de confirmation}';

    protected $description = 'Restaurer une sauvegarde (base + documents). ATTENTION : remplace les données de la base cible.';

    public function handle(SauvegardeService $s): int
    {
        $base = $this->option('base') ?: config('database.connections.'.config('database.default').'.database');
        if (! $this->option('force')) {
            $this->warn("La base « $base » va être remplacée par le contenu de la sauvegarde.");
            if (text('Tapez le nom de la base pour confirmer') !== $base) {
                $this->error('Confirmation incorrecte : restauration annulée.');

                return self::FAILURE;
            }
        }
        try {
            $r = $s->restaurer($this->argument('archive'), $this->option('base'), ! $this->option('sans-fichiers'));
            $this->info("Restauration terminée dans « {$r['base']} » (sauvegarde du {$r['date_sauvegarde']}, {$r['fichiers']} fichier(s)).");
            if ($r['protection_audit'] === false) {
                $this->warn('Déclencheurs de protection du journal non recréés : exécutez database/sql/protection_audit.sql.');
            }
            $this->line('Pensez à exécuter : php artisan migrate --force && php artisan optimize:clear');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Échec de la restauration : '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
