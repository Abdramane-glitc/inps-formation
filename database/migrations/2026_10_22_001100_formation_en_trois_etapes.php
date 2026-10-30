<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Version 1.5.0 — parcours en trois étapes décidé par l'INPS (22/10/2026) : la formation, ses participants, les attestations.
 *  - accepter une demande = programmer la formation (dates, lieu, organisme) et y inscrire les agents de la demande ;
 *  - à la fin, la RH coche les participants qui ont suivi la formation ;
 *  - plus de convocations, de démarrage, de présences jour par jour, de liste d'attente ni d'évaluations.
 *
 * Aucune donnée n'est supprimée (présences, évaluations, convocations restent en base). Les formations « confirmées »,
 * « en cours » ou « reportées » redeviennent « programmées » (statut technique planifiee) : il reste à les marquer terminées.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sessions = DB::table('sessions_formation')->whereIn('statut', ['confirmee', 'en_cours', 'reportee'])->pluck('id');
        DB::table('sessions_formation')->whereIn('id', $sessions)->update(['statut' => 'planifiee', 'updated_at' => now()]);
        DB::table('actions_formation')->whereIn('statut', ['en_cours', 'reportee'])->update(['statut' => 'planifiee', 'updated_at' => now()]);

        DB::table('permissions')->where('code', 'like', 'presences.%')->orWhere('code', 'like', 'evaluations.%')->delete(); // permission_role en cascade
        DB::table('parametres')->where('cle', 'like', 'regles.%')
            ->orWhereIn('cle', ['notifications.delai_convocation_jours', 'notifications.delai_evaluation_impact_mois'])->delete();
        DB::table('permissions')->where('code', 'inscriptions.gerer')->update(['libelle' => 'Gérer les participants des formations', 'updated_at' => now()]);
        Cache::forget('parametres.tous'); // quel que soit le stockage du cache

        DB::table('audit_logs')->insert([
            'action' => 'migration.formation_trois_etapes',
            'description' => 'Version 1.5.0 : formation en trois étapes (formation, participants, attestations).',
            'contexte' => json_encode(['formations_remises_programmees' => $sessions->count()]),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Les permissions et paramètres retirés sont réinstallés par « php artisan db:seed » avec le code de la version 1.4.0.
    }
};
