<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1.7.0 — plan annuel simple (décision de l'INPS du 23/10/2026) : la RH inscrit les formations prévues
 * par période, puis « Programmer » crée la formation ; validation en un clic. Nouvelles tables, distinctes de celles
 * du plan retiré en 1.3.0 (plans_formation, plan_versions, plan_actions), qui restent en base sans usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_annuels', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('annee')->unique();
            $t->timestamp('valide_le')->nullable();
            $t->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $t->string('commentaire_validation', 1000)->nullable();
            $t->timestamps();
        });
        Schema::create('plan_annuel_lignes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained('plans_annuels')->restrictOnDelete();
            $t->string('intitule', 255);
            $t->string('periode', 10); // annee, T1..T4, M01..M12
            $t->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $t->unsignedInteger('nb_agents')->nullable();
            $t->string('type', 20)->nullable();
            $t->foreignId('organisme_id')->nullable()->constrained('organismes')->nullOnDelete();
            $t->text('observations')->nullable();
            $t->foreignId('besoin_id')->nullable()->unique()->constrained('besoins_formation')->nullOnDelete();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['plan_id', 'periode']);
        });
        Schema::table('actions_formation', function (Blueprint $t) {
            $t->foreignId('ligne_plan_id')->nullable()->after('besoin_id_origine')->constrained('plan_annuel_lignes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('actions_formation', function (Blueprint $t) {
            $t->dropConstrainedForeignId('ligne_plan_id');
        });
        Schema::dropIfExists('plan_annuel_lignes');
        Schema::dropIfExists('plans_annuels');
    }
};
