<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recensement des besoins, circuits de validation, décisions et plans annuels versionnés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('besoins_formation', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedSmallInteger('annee')->index();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('intitule', 255);
            $table->text('contexte')->nullable();
            $table->text('competences_visees')->nullable();
            $table->text('objectifs')->nullable();
            $table->text('publics_concernes')->nullable(); // catégories d'agents concernées
            $table->unsignedInteger('nb_participants')->nullable();
            $table->foreignId('priorite_id')->nullable()->constrained('nomenclatures')->nullOnDelete();
            $table->foreignId('type_formation_id')->nullable()->constrained('nomenclatures')->nullOnDelete();
            $table->foreignId('domaine_id')->nullable()->constrained('domaines')->nullOnDelete();
            $table->decimal('duree_estimee_jours', 6, 1)->nullable();
            $table->string('periode_souhaitee', 100)->nullable();
            $table->decimal('cout_previsionnel', 15, 2)->nullable();
            $table->string('statut', 30)->default('brouillon')->index();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('soumis_le')->nullable();
            $table->timestamps();
            $table->softDeletes(); // seuls les brouillons peuvent être retirés
            $table->index(['annee', 'statut']);
            $table->index(['service_id', 'annee']);
        });

        // Agents nommément concernés (facultatif)
        Schema::create('besoin_agent', function (Blueprint $table) {
            $table->foreignId('besoin_id')->constrained('besoins_formation')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->primary(['besoin_id', 'agent_id']);
        });

        // Circuits de validation configurables (objet : plan)
        Schema::create('circuits_validation', function (Blueprint $table) {
            $table->id();
            $table->string('objet', 30)->index(); // plan
            $table->string('nom', 190);
            $table->boolean('actif')->default(false);
            $table->timestamps();
        });

        Schema::create('etapes_validation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circuit_id')->constrained('circuits_validation')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordre');
            $table->string('libelle', 190);
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['circuit_id', 'ordre']);
        });

        Schema::create('plans_formation', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->string('titre', 255);
            $table->text('objectifs')->nullable();
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans_formation')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            // brouillon | soumise | validee | rejetee | remplacee
            $table->string('statut', 20)->default('brouillon')->index();
            $table->foreignId('circuit_id')->nullable()->constrained('circuits_validation')->nullOnDelete();
            $table->unsignedSmallInteger('etape_courante')->nullable();
            $table->text('motif_revision')->nullable();
            $table->decimal('budget_global', 15, 2)->nullable(); // enveloppe annoncée (facultative)
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('soumise_le')->nullable();
            $table->timestamp('validee_le')->nullable();
            $table->timestamp('verrouillee_le')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'numero']);
        });

        Schema::create('plan_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_version_id')->constrained('plan_versions')->cascadeOnDelete();
            // Code stable d'une version à l'autre (permet la comparaison prévisionnel / réalisé)
            $table->string('code', 30);
            $table->foreignId('besoin_id')->nullable()->constrained('besoins_formation')->nullOnDelete();
            $table->string('intitule', 255);
            $table->foreignId('axe_id')->nullable()->constrained('nomenclatures')->nullOnDelete();
            $table->foreignId('domaine_id')->nullable()->constrained('domaines')->nullOnDelete();
            $table->foreignId('direction_id')->nullable()->constrained('directions')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('priorite_id')->nullable()->constrained('nomenclatures')->nullOnDelete();
            $table->string('type', 10)->default('externe'); // interne | externe
            $table->text('objectifs')->nullable();
            $table->string('public_cible', 255)->nullable();
            $table->unsignedInteger('nb_participants')->nullable();
            $table->decimal('cout_estime', 15, 2)->default(0);
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->timestamps();
            $table->unique(['plan_version_id', 'code']);
            $table->unique(['plan_version_id', 'besoin_id']);
        });

        // Historique des décisions (besoins, versions de plan) : auteur, date, motif
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('decidable');
            $table->string('action', 40); // soumission, mise_en_examen, demande_complement, acceptation, rejet, integration, validation_etape, rejet_etape, revision…
            $table->string('statut_avant', 30)->nullable();
            $table->string('statut_apres', 30)->nullable();
            $table->unsignedSmallInteger('etape_ordre')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
        Schema::dropIfExists('plan_actions');
        Schema::dropIfExists('plan_versions');
        Schema::dropIfExists('plans_formation');
        Schema::dropIfExists('etapes_validation');
        Schema::dropIfExists('circuits_validation');
        Schema::dropIfExists('besoin_agent');
        Schema::dropIfExists('besoins_formation');
    }
};
