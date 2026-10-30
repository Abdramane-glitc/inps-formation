<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Version 2.5.1 — correctif : un plan annuel marqué « validé » alors qu'il ne contient plus aucune formation
 * (toutes retirées après la validation) affichait « Plan validé le … ». Sa validation est effacée.
 * Les plans qui contiennent des formations ne sont pas touchés.
 */
return new class extends Migration
{
    public function up(): void
    {
        $vides = DB::table('plans_annuels')->whereNotNull('valide_le')
            ->whereNotExists(fn ($q) => $q->from('plan_annuel_lignes')->whereColumn('plan_annuel_lignes.plan_id', 'plans_annuels.id')->whereNull('plan_annuel_lignes.deleted_at'))
            ->pluck('annee');
        if ($vides->isEmpty()) {
            return;
        }
        DB::table('plans_annuels')->whereIn('annee', $vides)->update(['valide_le' => null, 'valide_par' => null, 'commentaire_validation' => null, 'updated_at' => now()]);
        DB::table('audit_logs')->insert([
            'action' => 'migration.plans_valides_vides',
            'description' => 'Version 2.5.1 : validation effacée pour les plans sans formation ('.$vides->implode(', ').')',
            'contexte' => json_encode(['annees' => $vides->all()]),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Correctif de données : rien à restaurer.
    }
};
