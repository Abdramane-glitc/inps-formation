<?php

use App\Support\CataloguePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Version 1.6.0 — deux profils fixes (RH, Chef de service) et libellés des droits en langage courant.
 * Aucune donnée supprimée : seuls le module et le libellé des permissions existantes sont réécrits
 * à partir du catalogue ; rôles, attributions et codes techniques sont inchangés.
 */
return new class extends Migration
{
    public function up(): void
    {
        $n = 0;
        foreach (CataloguePermissions::PERMISSIONS as $code => [$module, $libelle]) {
            $n += DB::table('permissions')->where('code', $code)->update(['module' => $module, 'libelle' => $libelle, 'updated_at' => now()]);
        }
        DB::table('audit_logs')->insert([
            'action' => 'migration.profils_fixes',
            'description' => 'Version 1.6.0 : deux profils fixes (RH, Chef de service), libellés des droits simplifiés.',
            'contexte' => json_encode(['permissions_relibellees' => $n]),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Libellés seulement : rien à restaurer.
    }
};
