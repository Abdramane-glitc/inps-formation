<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1.3.0 — parcours simplifié (décision INPS du 21/10/2026) :
 *   demande → acceptée → formation organisée (dates, participants, présences, attestations).
 *  - le plan annuel est retiré de l'application ;
 *  - une formation = une action + UNE session ; le statut de l'action suit celui de la session ;
 *  - une formation peut être rattachée à la demande qui l'a motivée (actions_formation.besoin_id).
 *
 * Aucune donnée n'est supprimée : les tables du plan (plans_formation, plan_versions, plan_actions,
 * circuits_validation, etapes_validation) et les colonnes actions_formation.plan_id / plan_action_code
 * sont conservées pour l'historique, sans usage par l'application.
 */
return new class extends Migration
{
    private const STATUT_SELON_SESSION = [
        'planifiee' => 'planifiee', 'confirmee' => 'planifiee', 'en_cours' => 'en_cours',
        'terminee' => 'realisee', 'annulee' => 'annulee', 'reportee' => 'reportee',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('actions_formation', 'besoin_id')) {
            Schema::table('actions_formation', function (Blueprint $table) {
                $table->foreignId('besoin_id')->nullable()->after('plan_action_code')->constrained('besoins_formation')->nullOnDelete();
                $table->unique('besoin_id');
            });
        }

        $bilan = ['demandes_rattachees' => 0, 'demandes_organisees' => 0, 'demandes_remises_acceptees' => 0, 'formations_scindees' => 0, 'statuts_alignes' => 0];

        // 1. Rattacher chaque formation issue d'une ligne de plan à la demande d'origine de cette ligne.
        $liens = DB::table('actions_formation as a')
            ->join('plan_versions as pv', 'pv.plan_id', '=', 'a.plan_id')
            ->join('plan_actions as pa', fn ($j) => $j->on('pa.plan_version_id', '=', 'pv.id')->on('pa.code', '=', 'a.plan_action_code'))
            ->whereNotNull('a.plan_id')->whereNotNull('pa.besoin_id')->whereNull('a.besoin_id')
            ->orderBy('a.id')->orderByDesc('pv.numero')
            ->get(['a.id as action_id', 'pa.besoin_id']);
        $pris = DB::table('actions_formation')->whereNotNull('besoin_id')->pluck('besoin_id')->flip();
        $faits = [];
        foreach ($liens as $l) {
            if (isset($faits[$l->action_id]) || isset($pris[$l->besoin_id])) {
                continue;
            }
            DB::table('actions_formation')->where('id', $l->action_id)->update(['besoin_id' => $l->besoin_id]);
            $faits[$l->action_id] = true;
            $pris[$l->besoin_id] = true;
            $bilan['demandes_rattachees']++;
        }

        // 2. Demandes « intégrées au plan » (ou acceptées) : « formation organisée » si une formation y répond, sinon « acceptée ».
        foreach (DB::table('besoins_formation')->whereIn('statut', ['integree_plan', 'acceptee'])->pluck('id') as $id) {
            $organisee = DB::table('actions_formation')->where('besoin_id', $id)->exists();
            $statut = $organisee ? 'organisee' : 'acceptee';
            if (DB::table('besoins_formation')->where('id', $id)->where('statut', '!=', $statut)->update(['statut' => $statut, 'updated_at' => now()])) {
                $bilan[$organisee ? 'demandes_organisees' : 'demandes_remises_acceptees']++;
            }
        }

        // 3. Une formation = une session : une action à plusieurs sessions devient une formation par session.
        //    La première session reste sur l'action d'origine (avec ses dépenses et documents).
        $multiples = DB::table('sessions_formation')->select('action_id')->groupBy('action_id')->havingRaw('COUNT(*) > 1')->pluck('action_id');
        foreach ($multiples as $actionId) {
            $action = (array) DB::table('actions_formation')->where('id', $actionId)->first();
            $sessions = DB::table('sessions_formation')->where('action_id', $actionId)->orderBy('date_debut')->orderBy('id')->get();
            foreach ($sessions->slice(1)->values() as $rang => $s) {
                $copie = $action;
                unset($copie['id']);
                $copie['reference'] = mb_substr($action['reference'], 0, 26).'-G'.($rang + 2);
                $copie['besoin_id'] = null;
                $copie['statut'] = self::STATUT_SELON_SESSION[$s->statut] ?? 'planifiee';
                $copie['created_at'] = now();
                $copie['updated_at'] = now();
                $nouvelle = DB::table('actions_formation')->insertGetId($copie);
                DB::table('sessions_formation')->where('id', $s->id)->update(['action_id' => $nouvelle]);
                $bilan['formations_scindees']++;
            }
        }

        // 4. Le statut de chaque formation suit désormais celui de sa session.
        foreach (DB::table('sessions_formation')->get(['action_id', 'statut']) as $s) {
            $attendu = self::STATUT_SELON_SESSION[$s->statut] ?? null;
            if ($attendu && DB::table('actions_formation')->where('id', $s->action_id)->where('statut', '!=', $attendu)->update(['statut' => $attendu, 'updated_at' => now()])) {
                $bilan['statuts_alignes']++;
            }
        }

        // 5. Permissions et paramètres du plan annuel retirés ; libellés « Formations ».
        DB::table('permissions')->where('code', 'like', 'plans.%')->delete(); // permission_role : suppression en cascade
        DB::table('permissions')->where('code', 'actions.voir')->update(['module' => 'Formations', 'libelle' => 'Consulter les formations', 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'actions.gerer')->update(['module' => 'Formations', 'libelle' => 'Organiser les formations (dates, reports, annulations)', 'updated_at' => now()]);
        DB::table('roles')->where('code', 'responsable_drh')->update([
            'description' => 'Gestionnaire principal : demandes, organisation des formations, participants, évaluations, attestations, dépenses, paramètres et rapports.',
            'updated_at' => now(),
        ]);
        DB::table('roles')->where('code', 'autorite_validation')->update([
            'description' => 'Consultation des demandes, des formations et des rapports.',
            'updated_at' => now(),
        ]);
        DB::table('parametres')->where('cle', 'plans.interdire_auto_validation')->delete();
        DB::table('circuits_validation')->update(['actif' => false]);
        DB::table('cache')->where('key', 'like', '%parametres.tous%')->delete();

        DB::table('audit_logs')->insert([
            'action' => 'migration.formations_sans_plan',
            'description' => 'Version 1.3.0 : plan annuel retiré, une formation = une session, formations rattachées aux demandes.',
            'contexte' => json_encode($bilan),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('besoins_formation')->where('statut', 'organisee')->update(['statut' => 'acceptee']);
        if (Schema::hasColumn('actions_formation', 'besoin_id')) {
            Schema::table('actions_formation', function (Blueprint $table) {
                $table->dropConstrainedForeignId('besoin_id');
            });
        }
        // Les permissions du plan sont réinstallées par « php artisan db:seed » avec le code de la version 1.2.0.
    }
};
