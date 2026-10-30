<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1.8.0 — analyse du besoin dans la demande de formation.
 * « contexte » devient le problème constaté dans le service (les textes existants sont conservés tels quels) ;
 * s'ajoutent l'origine du problème (cases à cocher), la raison pour laquelle une formation le réglerait et
 * ce que les agents devront savoir faire après la formation (repris pour l'évaluation à 3–6 mois).
 * Champs facultatifs en base : les demandes antérieures n'en ont pas ; ils sont obligatoires dans le formulaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('besoins_formation', function (Blueprint $t) {
            $t->json('causes')->nullable()->after('contexte');
            $t->text('justification_formation')->nullable()->after('causes');
            $t->text('resultat_attendu')->nullable()->after('justification_formation');
        });
    }

    public function down(): void
    {
        Schema::table('besoins_formation', function (Blueprint $t) {
            $t->dropColumn(['causes', 'justification_formation', 'resultat_attendu']);
        });
    }
};
