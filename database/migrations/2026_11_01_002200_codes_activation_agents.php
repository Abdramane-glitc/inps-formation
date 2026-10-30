<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2.4.0 — accès des agents simplifié (demande de l'INPS du 28/10/2026) : la RH génère un code d'activation
 * de 6 chiffres, visible à l'écran ; l'agent l'utilise une fois pour choisir son mot de passe.
 * Le code est chiffré en base (il doit pouvoir être réaffiché à la RH) ; 5 essais au plus ; expiration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('code_activation')->nullable()->after('doit_changer_mot_de_passe'); // chiffré (APP_KEY)
            $t->timestamp('code_activation_expire_le')->nullable()->after('code_activation');
            $t->unsignedTinyInteger('code_activation_essais')->default(0)->after('code_activation_expire_le');
            $t->timestamp('active_le')->nullable()->after('code_activation_essais');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['code_activation', 'code_activation_expire_le', 'code_activation_essais', 'active_le']));
    }
};
