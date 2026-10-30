<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structure organisationnelle : directions, services, et référentiels de paramétrage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 190);
            $table->boolean('actif')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_id')->constrained('directions')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('nom', 190);
            $table->boolean('actif')->default(true)->index();
            $table->timestamps();
            $table->index(['direction_id', 'actif']);
        });

        // Domaines de compétence / formation
        Schema::create('domaines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 190);
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Nomenclatures paramétrables : priorités, types de formation, statuts professionnels,
        // axes stratégiques, catégories de dépenses, catégories de documents…
        Schema::create('nomenclatures', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('code', 50);
            $table->string('libelle', 190);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['type', 'code']);
            $table->index(['type', 'actif', 'ordre']);
        });

        Schema::create('annees_reference', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->string('statut', 20)->default('ouverte'); // ouverte | cloturee
            $table->date('date_limite_besoins')->nullable();
            $table->date('date_cloture')->nullable();
            $table->timestamps();
        });

        // Paramètres institutionnels (clé / valeur typée)
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 100)->unique();
            $table->text('valeur')->nullable();
            $table->string('type', 20)->default('string'); // string|int|decimal|bool|text|file
            $table->string('groupe', 50)->index();
            $table->string('libelle', 190);
            $table->string('aide', 500)->nullable();
            $table->boolean('a_valider_inps')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('annees_reference');
        Schema::dropIfExists('nomenclatures');
        Schema::dropIfExists('domaines');
        Schema::dropIfExists('services');
        Schema::dropIfExists('directions');
    }
};
