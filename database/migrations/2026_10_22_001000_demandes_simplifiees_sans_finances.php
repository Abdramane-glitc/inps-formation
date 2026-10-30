<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Version 1.4.0 — simplification décidée par l'INPS (22/10/2026) :
 *  - demande de formation minimale, envoyée directement : envoyée → acceptée / refusée / à compléter ;
 *    l'étape « En examen » disparaît (les demandes « en examen » redeviennent « envoyées ») ;
 *  - plus de budget ni de finances dans l'application : permissions « finances.* » et rôle
 *    « Gestionnaire financier » retirés (s'il n'est attribué à personne).
 *
 * Aucune donnée n'est supprimée : la table depenses, les coûts et montants déjà saisis restent en base, sans usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        $examen = DB::table('besoins_formation')->where('statut', 'en_examen')->update(['statut' => 'soumise', 'updated_at' => now()]);

        DB::table('parametres')->whereIn('cle', ['besoins.statut_en_examen_actif', 'besoins.statut_a_completer_actif'])->delete();

        DB::table('permissions')->where('code', 'like', 'finances.%')->delete(); // permission_role : suppression en cascade
        $role = DB::table('roles')->where('code', 'gestionnaire_financier')->value('id');
        $roleRetire = false;
        if ($role && ! DB::table('role_user')->where('role_id', $role)->exists() && ! DB::table('etapes_validation')->where('role_id', $role)->exists()) {
            DB::table('roles')->where('id', $role)->delete();
            $roleRetire = true;
        }
        DB::table('permissions')->where('code', 'besoins.instruire')
            ->update(['libelle' => 'Décider des demandes (accepter, demander des précisions, refuser)', 'updated_at' => now()]);
        DB::table('roles')->where('code', 'responsable_drh')->update([
            'description' => 'Gestionnaire principal : demandes, organisation des formations, participants, évaluations, attestations, paramètres et rapports.',
            'updated_at' => now(),
        ]);
        DB::table('cache')->where('key', 'like', '%parametres.tous%')->delete();

        DB::table('audit_logs')->insert([
            'action' => 'migration.demandes_simplifiees',
            'description' => 'Version 1.4.0 : demandes simplifiées (sans « En examen »), budget et finances retirés de l\'application.',
            'contexte' => json_encode(['demandes_en_examen_remises_envoyees' => $examen, 'role_gestionnaire_financier_retire' => $roleRetire]),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Les permissions et paramètres retirés sont réinstallés par « php artisan db:seed » avec le code de la version 1.3.x.
    }
};
