<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dépenses, documents, notifications, journal d'audit et importations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('actions_formation')->restrictOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('sessions_formation')->nullOnDelete();
            $table->foreignId('categorie_id')->constrained('nomenclatures')->restrictOnDelete();
            $table->string('libelle', 255);
            $table->foreignId('organisme_id')->nullable()->constrained('organismes')->nullOnDelete();
            $table->string('beneficiaire', 190)->nullable();
            $table->decimal('montant', 15, 2);
            // engagee | payee | annulee  (le suivi ne remplace pas la comptabilité institutionnelle)
            $table->string('statut', 20)->default('engagee')->index();
            $table->date('date_engagement');
            $table->string('numero_facture', 100)->nullable();
            $table->date('date_facture')->nullable();
            $table->date('date_paiement')->nullable();
            $table->string('reference_paiement', 100)->nullable();
            $table->boolean('rapprochee')->default(false);
            $table->text('observations')->nullable();
            $table->foreignId('saisi_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['action_id', 'statut']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->foreignId('categorie_id')->nullable()->constrained('nomenclatures')->nullOnDelete();
            $table->string('titre', 255);
            $table->string('nom_original', 255);
            $table->string('chemin', 255)->unique(); // nom de stockage aléatoire, disque privé
            $table->string('mime', 120);
            $table->unsignedBigInteger('taille');
            $table->char('sha256', 64);
            $table->boolean('confidentiel')->default(false);
            $table->foreignId('depose_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Évite l'envoi en double des rappels automatiques
        Schema::create('rappels_envoyes', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 190)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        // Suivi réel des envois e-mail (aucun envoi n'est déclaré réussi sans retour du transport)
        Schema::create('envois_email', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sujet', 255);
            $table->string('statut', 20); // envoye | echec | desactive
            $table->string('erreur', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('objet_type', 80)->nullable();
            $table->unsignedBigInteger('objet_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('contexte')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['objet_type', 'objet_id']);
        });

        // Protection du journal contre les modifications ordinaires (MySQL)
        // Si le compte MySQL de l'application n'a pas le droit de créer des déclencheurs
        // (binlog actif sans SUPER), la migration continue : l'administrateur de base exécute
        // alors database/sql/protection_audit.sql (voir docs/SECURITE.md). La commande
        // « php artisan inps:sante » signale l'absence des déclencheurs.
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non modifiable'");
                DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non supprimable'");
            } catch (QueryException $e) {
                fwrite(STDERR, "AVERTISSEMENT : déclencheurs de protection du journal d'audit non créés (".$e->getCode()."). Exécuter database/sql/protection_audit.sql avec un compte administrateur MySQL.\n");
            }
        }

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // agents | historique
            $table->string('nom_fichier', 255);
            $table->string('statut', 20)->default('analyse'); // analyse | termine | echoue | abandonne
            $table->unsignedInteger('total_lignes')->default(0);
            $table->unsignedInteger('lignes_valides')->default(0);
            $table->unsignedInteger('lignes_erreur')->default(0);
            $table->unsignedInteger('lignes_doublon')->default(0);
            $table->unsignedInteger('lignes_importees')->default(0);
            $table->text('message')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('termine_le')->nullable();
            $table->timestamps();
        });

        Schema::create('import_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->json('donnees');
            $table->string('statut', 20); // valide | erreur | doublon | importee
            $table->json('messages')->nullable();
            $table->index(['import_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_lignes');
        Schema::dropIfExists('imports');
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        }
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('envois_email');
        Schema::dropIfExists('rappels_envoyes');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('depenses');
    }
};
