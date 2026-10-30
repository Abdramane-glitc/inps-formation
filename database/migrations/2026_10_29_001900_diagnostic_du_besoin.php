<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2.1.0 — analyse du besoin revue (demande de l'INPS du 27/10/2026) :
 *  - « D'où vient ce problème ? » devient un diagnostic en trois questions (savoir-faire, moyens, temps) ;
 *  - gravité du problème, mesure (situation actuelle → objectif), solutions déjà essayées.
 * La colonne « causes » (1.8.0) est conservée pour les demandes déjà enregistrées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('besoins_formation', function (Blueprint $t) {
            $t->string('diag_savoir', 10)->nullable()->after('causes');   // non | partiel | oui
            $t->string('diag_moyens', 5)->nullable()->after('diag_savoir'); // oui | non
            $t->string('diag_temps', 5)->nullable()->after('diag_moyens');  // oui | non
            $t->string('gravite', 10)->nullable()->after('diag_temps');     // legere | reguliere | risque
            $t->string('situation_actuelle', 500)->nullable()->after('gravite');
            $t->string('objectif_vise', 500)->nullable()->after('situation_actuelle');
            $t->json('solutions_essayees')->nullable()->after('objectif_vise');
        });
    }

    public function down(): void
    {
        Schema::table('besoins_formation', function (Blueprint $t) {
            $t->dropColumn(['diag_savoir', 'diag_moyens', 'diag_temps', 'gravite', 'situation_actuelle', 'objectif_vise', 'solutions_essayees']);
        });
    }
};
