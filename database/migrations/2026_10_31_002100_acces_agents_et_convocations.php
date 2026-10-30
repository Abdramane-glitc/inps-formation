<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2.3.0 (demandes de l'INPS du 27/10/2026) :
 *  - chaque agent peut avoir un accès à l'application (profil « Agent »), avec son matricule comme identifiant :
 *    l'adresse e-mail d'un compte devient facultative (beaucoup d'agents n'en ont pas) ;
 *  - convocation des participants : date et détails (dates, horaires, lieu) de la dernière convocation de la formation,
 *    pour savoir s'il faut une convocation rectificative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('email', 190)->nullable()->change();
        });
        Schema::table('sessions_formation', function (Blueprint $t) {
            $t->timestamp('convoquee_le')->nullable()->after('rapport_redige_par');
            $t->json('convocation_details')->nullable()->after('convoquee_le');
        });
        DB::table('roles')->where('code', 'agent')->update(['description' => 'Consulte ses formations, ses convocations et ses attestations.', 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'espace.personnel')->update(['libelle' => 'Consulter ses propres formations, convocations et attestations', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('sessions_formation', fn (Blueprint $t) => $t->dropColumn(['convoquee_le', 'convocation_details']));
    }
};
