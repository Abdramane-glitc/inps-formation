<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1.9.0 — après la formation (demandes de l'INPS du 26/10/2026) :
 *  - évaluation à chaud saisie par la RH depuis les fiches papier (table evaluations_satisfaction, existante) ;
 *  - évaluation 3 à 6 mois après par le chef de service (table evaluations_impact, existante, + « résultat atteint ») ;
 *    le délai est choisi par la RH pour chaque formation (sessions_formation.evaluation_froid_mois) ;
 *  - rapport de réalisation (zones de texte de la RH sur la session ; le reste est calculé) ;
 *  - attestation d'intervention des formateurs externes (nouvelle table attestations_intervenants).
 * Deux permissions : evaluations.gerer (RH) et evaluations.froid (chef de service, pour les agents de son service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions_formation', function (Blueprint $t) {
            $t->unsignedTinyInteger('evaluation_froid_mois')->nullable()->after('bilan_pedagogique');
            $t->text('rapport_deroulement')->nullable()->after('evaluation_froid_mois');
            $t->text('rapport_difficultes')->nullable()->after('rapport_deroulement');
            $t->text('rapport_recommandations')->nullable()->after('rapport_difficultes');
            $t->timestamp('rapport_redige_le')->nullable()->after('rapport_recommandations');
            $t->foreignId('rapport_redige_par')->nullable()->after('rapport_redige_le')->constrained('users')->nullOnDelete();
        });
        Schema::table('evaluations_impact', function (Blueprint $t) {
            $t->string('resultat_atteint', 10)->nullable()->after('niveau_mise_en_pratique'); // oui | partiel | non
        });
        Schema::create('attestations_intervenants', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->foreignId('session_id')->constrained('sessions_formation')->restrictOnDelete();
            $t->foreignId('formateur_id')->constrained('formateurs')->restrictOnDelete();
            $t->timestamp('emise_le');
            $t->foreignId('emise_par')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['session_id', 'formateur_id']);
        });

        $maintenant = now();
        $perms = [
            'evaluations.gerer' => ['Évaluations', 'Saisir les évaluations à chaud et suivre toutes les évaluations à 3–6 mois', false, ['responsable_drh']],
            'evaluations.froid' => ['Évaluations', 'Évaluer les agents de son service 3 à 6 mois après leur formation', false, ['chef_service']],
        ];
        foreach ($perms as $code => [$module, $libelle, $sensible, $roles]) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['module' => $module, 'libelle' => $libelle, 'sensible' => $sensible, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
            $pid = DB::table('permissions')->where('code', $code)->value('id');
            foreach (DB::table('roles')->whereIn('code', $roles)->pluck('id') as $rid) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $rid, 'permission_id' => $pid]);
            }
        }
        DB::table('audit_logs')->insert([
            'action' => 'migration.apres_formation',
            'description' => 'Version 1.9.0 : évaluations à chaud et à 3–6 mois, rapport de réalisation, attestation des formateurs externes.',
            'created_at' => $maintenant,
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('code', ['evaluations.gerer', 'evaluations.froid'])->delete();
        Schema::dropIfExists('attestations_intervenants');
        Schema::table('evaluations_impact', fn (Blueprint $t) => $t->dropColumn('resultat_atteint'));
        Schema::table('sessions_formation', function (Blueprint $t) {
            $t->dropConstrainedForeignId('rapport_redige_par');
            $t->dropColumn(['evaluation_froid_mois', 'rapport_deroulement', 'rapport_difficultes', 'rapport_recommandations', 'rapport_redige_le']);
        });
    }
};
