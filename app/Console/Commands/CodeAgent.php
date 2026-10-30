<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\AccesAgentService;
use Illuminate\Console\Command;

/** Dépannage (2.4.0) : donne un nouveau code d'activation à un agent et l'affiche dans la console (journalisé). */
class CodeAgent extends Command
{
    protected $signature = 'inps:code-agent {matricule : Matricule de l\'agent}';

    protected $description = 'Donner un nouveau code d\'activation à un agent (affiché dans la console)';

    public function handle(AccesAgentService $acces): int
    {
        $ag = Agent::where('matricule', mb_strtoupper(trim($this->argument('matricule'))))->first();
        if (! $ag || ! $ag->actif) {
            $this->error('Aucun agent actif avec ce matricule.');

            return self::FAILURE;
        }
        $acces->genererCodes([$ag->id], null, nouveau: true);
        $u = $ag->user()->first();
        $this->info("Agent : {$ag->nom_complet} — matricule {$ag->matricule}");
        $this->line("Code d'activation : {$u->code_activation_affiche} (valable jusqu'au {$u->code_activation_expire_le->format('d/m/Y')})");
        $this->line('L\'agent clique sur « Activer mon compte », saisit son matricule et ce code, puis choisit son mot de passe.');

        return self::SUCCESS;
    }
}
