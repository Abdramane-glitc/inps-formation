<?php

namespace App\Console\Commands;

use App\Services\SauvegardeService;
use Illuminate\Console\Command;
use Throwable;

class Sauvegarder extends Command
{
    protected $signature = 'inps:sauvegarde';

    protected $description = 'Sauvegarder la base de données et les documents privés (archive ZIP, chiffrée si BACKUP_PASSWORD est défini)';

    public function handle(SauvegardeService $s): int
    {
        try {
            $chemin = $s->sauvegarder();
            cache()->forever('inps.derniere_sauvegarde', now()->toIso8601String());
            $this->info('Sauvegarde créée : '.$chemin.' ('.number_format(filesize($chemin) / 1024, 0, ',', ' ').' Ko)');
            if (! config('inps.sauvegarde.mot_de_passe')) {
                $this->warn('BACKUP_PASSWORD n\'est pas défini : l\'archive n\'est PAS chiffrée.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Échec de la sauvegarde : '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }
    }
}
