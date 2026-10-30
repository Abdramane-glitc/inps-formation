<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sigle des directions (DRH, DSI, DR KAYES…), repris de la liste officielle des directions (29/10/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directions', function (Blueprint $table) {
            $table->string('sigle', 30)->nullable()->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('directions', function (Blueprint $table) {
            $table->dropColumn('sigle');
        });
    }
};
