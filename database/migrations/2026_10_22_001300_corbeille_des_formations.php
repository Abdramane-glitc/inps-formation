<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1.6.0 — suppression d'une formation saisie par erreur et corbeille (restauration possible).
 * Suppression logique : la ligne reste en base avec sa date de suppression. La demande d'origine est
 * détachée (besoin_id est unique) et mémorisée dans besoin_id_origine pour une éventuelle restauration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actions_formation', function (Blueprint $t) {
            $t->softDeletes();
            $t->unsignedBigInteger('besoin_id_origine')->nullable()->after('besoin_id');
        });
    }

    public function down(): void
    {
        Schema::table('actions_formation', function (Blueprint $t) {
            $t->dropColumn(['deleted_at', 'besoin_id_origine']);
        });
    }
};
