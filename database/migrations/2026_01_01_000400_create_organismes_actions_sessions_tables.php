<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organismes, formateurs, actions de formation, sessions et séances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organismes', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale', 190);
            $table->string('sigle', 50)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('site_web', 190)->nullable();
            $table->text('domaines_intervention')->nullable();
            $table->string('identifiant_fiscal', 60)->nullable(); // donnée contractuelle, accès restreint
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index('raison_sociale');
        });

        Schema::create('contacts_organisme', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisme_id')->constrained('organismes')->cascadeOnDelete();
            $table->string('nom', 190);
            $table->string('fonction', 190)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->timestamps();
        });

        Schema::create('conventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisme_id')->constrained('organismes')->cascadeOnDelete();
            $table->string('reference', 100);
            $table->string('objet', 255);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('montant', 15, 2)->nullable();
            $table->timestamps();
            $table->unique(['organisme_id', 'reference']);
        });

        Schema::create('formateurs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // interne | externe
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('organisme_id')->nullable()->constrained('organismes')->nullOnDelete();
            $table->string('nom', 100);
            $table->string('prenom', 150)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->text('domaines_expertise')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('actions_formation', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedSmallInteger('annee')->index();
            $table->string('intitule', 255);
            $table->text('description')->nullable();
            $table->foreignId('domaine_id')->nullable()->constrained('domaines')->nullOnDelete();
            $table->text('objectifs_pedagogiques')->nullable();
            $table->string('type', 10)->index(); // interne | externe
            $table->string('modalite', 15)->default('presentiel'); // presentiel | distanciel | hybride
            $table->foreignId('organisme_id')->nullable()->constrained('organismes')->nullOnDelete();
            $table->string('public_cible', 255)->nullable();
            $table->decimal('cout_previsionnel', 15, 2)->default(0);
            // planifiee | en_cours | realisee | reportee | annulee
            $table->string('statut', 20)->default('planifiee')->index();
            $table->string('motif_statut', 500)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained('plans_formation')->nullOnDelete();
            $table->string('plan_action_code', 30)->nullable();
            $table->boolean('historique_importe')->default(false); // reprise de l'historique
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['plan_id', 'plan_action_code']);
        });

        Schema::create('sessions_formation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('actions_formation')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->decimal('duree_heures', 6, 1)->nullable();
            $table->string('lieu', 255)->nullable();
            $table->unsignedInteger('places')->default(20);
            $table->boolean('liste_attente_active')->default(false);
            $table->foreignId('formateur_id')->nullable()->constrained('formateurs')->nullOnDelete();
            // planifiee | confirmee | en_cours | terminee | reportee | annulee
            $table->string('statut', 20)->default('planifiee')->index();
            $table->string('motif_statut', 500)->nullable();
            $table->text('bilan_pedagogique')->nullable();
            $table->timestamp('presences_validees_le')->nullable();
            $table->foreignId('presences_validees_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['date_debut', 'statut']);
        });

        // Historique des reports / changements de dates / annulations (rien n'est effacé)
        Schema::create('session_changements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions_formation')->cascadeOnDelete();
            $table->string('type', 30); // report | modification_dates | annulation | changement_statut
            $table->date('ancienne_date_debut')->nullable();
            $table->date('ancienne_date_fin')->nullable();
            $table->date('nouvelle_date_debut')->nullable();
            $table->date('nouvelle_date_fin')->nullable();
            $table->string('ancien_statut', 20)->nullable();
            $table->string('nouveau_statut', 20)->nullable();
            $table->string('motif', 500)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Séances (journées / demi-journées) servant à l'enregistrement des présences
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions_formation')->cascadeOnDelete();
            $table->date('date');
            $table->string('libelle', 100)->nullable();
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->timestamps();
            $table->index(['session_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
        Schema::dropIfExists('session_changements');
        Schema::dropIfExists('sessions_formation');
        Schema::dropIfExists('actions_formation');
        Schema::dropIfExists('formateurs');
        Schema::dropIfExists('conventions');
        Schema::dropIfExists('contacts_organisme');
        Schema::dropIfExists('organismes');
    }
};
