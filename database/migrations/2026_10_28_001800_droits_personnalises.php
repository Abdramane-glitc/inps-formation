<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2.0.0 — droits personnalisables par compte (demande de l'INPS du 26/10/2026).
 * Le profil (RH, Chef de service) reste le point de départ ; un compte peut recevoir des droits en plus
 * (accorde = true) ou en moins (accorde = false) par rapport à son profil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_user', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $t->boolean('accorde'); // true : droit ajouté au profil ; false : droit du profil retiré
            $t->foreignId('attribue_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->primary(['user_id', 'permission_id']);
        });
        DB::table('audit_logs')->insert([
            'action' => 'migration.droits_personnalises',
            'description' => 'Version 2.0.0 : droits personnalisables par compte, à partir du profil.',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
    }
};
