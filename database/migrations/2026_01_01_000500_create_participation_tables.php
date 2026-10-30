<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscriptions, présences, évaluations et attestations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions_formation')->restrictOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            // inscrit | liste_attente | convoque | confirme | desiste | remplace | annule
            $table->string('statut', 20)->default('inscrit')->index();
            $table->unsignedInteger('rang_attente')->nullable();
            $table->timestamp('convoque_le')->nullable();
            $table->timestamp('confirme_le')->nullable();
            $table->timestamp('desiste_le')->nullable();
            $table->string('motif_desistement', 500)->nullable();
            $table->foreignId('remplace_par_id')->nullable()->constrained('inscriptions')->nullOnDelete();
            // Résultat individuel calculé après validation des présences
            $table->decimal('taux_presence', 5, 2)->nullable();
            $table->string('resultat', 20)->nullable(); // achevee | non_achevee
            $table->timestamp('resultat_calcule_le')->nullable();
            $table->foreignId('inscrit_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['session_id', 'agent_id']);
            $table->index(['agent_id', 'resultat']);
        });

        Schema::create('inscription_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->string('evenement', 40);
            $table->string('details', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->string('statut', 20); // present | absent | retard | partiel
            $table->string('motif_absence', 500)->nullable(); // accès restreint
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['seance_id', 'inscription_id']);
        });

        Schema::create('presence_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presence_id')->constrained('presences')->cascadeOnDelete();
            $table->string('ancien_statut', 20);
            $table->string('nouveau_statut', 20);
            $table->string('motif', 500);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('evaluations_satisfaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->unique()->constrained('inscriptions')->cascadeOnDelete();
            $table->unsignedTinyInteger('note_globale');
            $table->unsignedTinyInteger('note_contenu')->nullable();
            $table->unsignedTinyInteger('note_formateur')->nullable();
            $table->unsignedTinyInteger('note_organisation')->nullable();
            $table->unsignedTinyInteger('note_utilite')->nullable();
            $table->text('commentaire')->nullable();
            $table->text('suggestions')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('evaluations_acquis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->unique()->constrained('inscriptions')->cascadeOnDelete();
            $table->string('methode', 50)->default('test'); // test | observation | projet
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('score_max', 6, 2)->nullable();
            $table->boolean('reussi');
            $table->text('appreciation')->nullable();
            $table->foreignId('evaluateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Évaluation différée de l'impact (mise en pratique des compétences)
        Schema::create('evaluations_impact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->date('date_evaluation');
            $table->unsignedTinyInteger('niveau_mise_en_pratique'); // 1 à 4
            $table->text('competences_observees')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('evaluateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('appreciations_prestataires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisme_id')->constrained('organismes')->cascadeOnDelete();
            $table->foreignId('action_id')->nullable()->constrained('actions_formation')->nullOnDelete();
            $table->unsignedTinyInteger('note'); // 1 à 5
            $table->text('commentaire')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('attestations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('inscription_id')->unique()->constrained('inscriptions')->restrictOnDelete();
            $table->string('code_verification', 64)->unique();
            $table->timestamp('emise_le');
            $table->foreignId('emise_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('annulee_le')->nullable();
            $table->string('motif_annulation', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('appreciations_prestataires');
        Schema::dropIfExists('evaluations_impact');
        Schema::dropIfExists('evaluations_acquis');
        Schema::dropIfExists('evaluations_satisfaction');
        Schema::dropIfExists('presence_corrections');
        Schema::dropIfExists('presences');
        Schema::dropIfExists('inscription_evenements');
        Schema::dropIfExists('inscriptions');
    }
};
