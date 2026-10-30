<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/**
 * Import depuis la console (29/10/2026), mêmes contrôles que l'écran « Importations » : analyse, puis confirmation
 * des seules lignes valides en une transaction. Utile pour les grandes listes (personnel) ou quand l'écran n'est pas disponible.
 * L'import est inscrit au nom d'un compte qui a le droit d'importer (option --email, sinon le premier compte actif qui l'a).
 */
class Importer extends Command
{
    protected $signature = 'inps:importer {type : structure, agents ou historique} {fichier : chemin du fichier CSV ou Excel}
        {--email= : compte au nom duquel l\'import est fait} {--confirmer : écrire les lignes valides (sinon : analyse seule)}
        {--tout-ou-rien : avec --confirmer, ne rien écrire si une seule ligne est en erreur ou en doublon}';

    protected $description = 'Importer un fichier (structure, agents, historique) depuis la console, avec les contrôles de l\'écran Importations';

    public function handle(ImportService $service): int
    {
        $type = (string) $this->argument('type');
        $chemin = (string) $this->argument('fichier');
        if (! isset(ImportService::COLONNES[$type])) {
            $this->error('Type inconnu : structure, agents ou historique.');

            return self::FAILURE;
        }
        if (! is_file($chemin)) {
            $this->error("Fichier introuvable : $chemin");

            return self::FAILURE;
        }
        $droit = $type === 'structure' ? 'parametres.gerer' : 'agents.importer';
        $u = $this->option('email')
            ? User::where('email', $this->option('email'))->first()
            : User::where('actif', true)->orderBy('id')->get()->first(fn (User $x) => $x->aPermission($droit));
        if (! $u || ! $u->aPermission($droit)) {
            $this->error("Aucun compte actif avec le droit « $droit ».");

            return self::FAILURE;
        }
        auth()->setUser($u);

        $import = $service->analyser(new UploadedFile($chemin, basename($chemin), null, null, true), $type, $u);
        $this->info(ImportService::LIBELLES[$type].' — '.basename($chemin)." (au nom de {$u->name})");
        $this->line("Lignes : {$import->total_lignes} — valides : {$import->lignes_valides} — erreurs : {$import->lignes_erreur} — doublons : {$import->lignes_doublon}");
        $groupes = $import->lignes()->whereIn('statut', ['erreur', 'doublon'])->get()->groupBy(fn ($l) => $l->statut.' : '.implode(' ', $l->messages));
        foreach ($groupes as $message => $lignes) {
            $this->line('  '.$lignes->count().' × '.$message.' (lignes '.$lignes->pluck('numero')->take(10)->implode(', ').($lignes->count() > 10 ? '…' : '').')');
        }
        if (! $this->option('confirmer')) {
            $this->line('Analyse seule : rien n\'est écrit. Confirmez dans l\'écran Importations, ou relancez avec --confirmer.');

            return self::SUCCESS;
        }
        if ($this->option('tout-ou-rien') && ($import->lignes_erreur + $import->lignes_doublon) > 0) {
            $import->update(['statut' => 'abandonne', 'message' => 'Abandonné : des lignes sont en erreur ou en doublon (option « tout ou rien »).']);
            $this->error('Des lignes sont en erreur ou en doublon : rien n\'est écrit (option « tout ou rien »).');

            return self::FAILURE;
        }
        if ($import->lignes_valides === 0) {
            $import->update(['statut' => 'abandonne', 'message' => 'Aucune ligne valide.']);
            $this->warn('Aucune ligne valide : rien n\'est écrit.');

            return self::SUCCESS;
        }
        $import = $service->confirmer($import, $u);
        $import->statut === 'termine' ? $this->info($import->message) : $this->error($import->message);

        return $import->statut === 'termine' ? self::SUCCESS : self::FAILURE;
    }
}
