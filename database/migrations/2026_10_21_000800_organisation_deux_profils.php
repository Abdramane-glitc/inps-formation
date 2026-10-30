<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Organisation retenue par l'INPS (21/10/2026) : deux profils.
 *  - la RH (rôle « responsable_drh ») gère tout le métier, valide le plan, saisit les dépenses et les paramètres ;
 *  - le chef de service déclare les besoins de son service.
 * Sur une base existante : ajoute les permissions au rôle RH, autorise la RH à valider le plan qu'elle a préparé,
 * et active un circuit de validation à une étape confiée à la RH (si aucun plan n'est en cours de validation).
 * Aucun compte n'est créé, modifié ni supprimé.
 */
return new class extends Migration
{
    private const PERMISSIONS_AJOUTEES = ['plans.valider', 'finances.gerer', 'finances.exporter', 'parametres.gerer'];

    private const NOM_CIRCUIT = 'Validation par la Direction des ressources humaines';

    public function up(): void
    {
        $roleRh = DB::table('roles')->where('code', 'responsable_drh')->value('id');
        if (! $roleRh) {
            return; // base vierge : le référentiel initial (db:seed) installera directement cette organisation
        }
        foreach (DB::table('permissions')->whereIn('code', self::PERMISSIONS_AJOUTEES)->pluck('id') as $permissionId) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $roleRh, 'permission_id' => $permissionId]);
        }
        DB::table('roles')->where('id', $roleRh)->update([
            'description' => 'Gestionnaire principal : besoins, plan annuel et sa validation, actions, participants, évaluations, attestations, dépenses, paramètres et rapports.',
            'updated_at' => now(),
        ]);

        DB::table('parametres')->where('cle', 'plans.interdire_auto_validation')->update([
            'valeur' => '0',
            'aide' => 'Désactivé : la RH prépare et valide le plan (organisation à deux profils). À activer si une autre personne ou autorité valide.',
            'updated_at' => now(),
        ]);

        // Un circuit utilisé par une version en cours de validation ne doit pas changer.
        if (DB::table('plan_versions')->where('statut', 'soumise')->exists()) {
            return;
        }
        $circuit = DB::table('circuits_validation')->where('objet', 'plan')->where('nom', self::NOM_CIRCUIT)->value('id');
        if (! $circuit) {
            $circuit = DB::table('circuits_validation')->insertGetId(['objet' => 'plan', 'nom' => self::NOM_CIRCUIT, 'actif' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('etapes_validation')->insert(['circuit_id' => $circuit, 'ordre' => 1, 'libelle' => self::NOM_CIRCUIT, 'role_id' => $roleRh, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('circuits_validation')->where('objet', 'plan')->update(['actif' => false]);
        DB::table('circuits_validation')->where('id', $circuit)->update(['actif' => true, 'updated_at' => now()]);
        DB::table('cache')->where('key', 'like', '%parametres.tous%')->delete();
    }

    public function down(): void
    {
        $roleRh = DB::table('roles')->where('code', 'responsable_drh')->value('id');
        if ($roleRh) {
            DB::table('permission_role')->where('role_id', $roleRh)
                ->whereIn('permission_id', DB::table('permissions')->whereIn('code', self::PERMISSIONS_AJOUTEES)->pluck('id'))->delete();
        }
        DB::table('parametres')->where('cle', 'plans.interdire_auto_validation')->update(['valeur' => '1']);
        DB::table('circuits_validation')->where('objet', 'plan')->where('nom', self::NOM_CIRCUIT)->update(['actif' => false]);
    }
};
