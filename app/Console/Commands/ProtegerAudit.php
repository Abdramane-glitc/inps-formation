<?php

namespace App\Console\Commands;

use App\Support\ProtectionAudit;
use Illuminate\Console\Command;

class ProtegerAudit extends Command
{
    protected $signature = 'inps:proteger-audit';

    protected $description = 'Installer les déclencheurs MySQL interdisant la modification du journal d\'audit';

    public function handle(): int
    {
        if (ProtectionAudit::installer()) {
            $this->info('Déclencheurs installés.');

            return self::SUCCESS;
        }
        $this->error('Installation impossible avec ce compte MySQL : exécutez database/sql/protection_audit.sql avec un compte administrateur.');

        return self::FAILURE;
    }
}
