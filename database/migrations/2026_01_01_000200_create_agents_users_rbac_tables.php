<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel du personnel, comptes utilisateurs et contrôle d'accès (rôles / permissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('nom', 100);
            $table->string('prenom', 150);
            $table->foreignId('direction_id')->nullable()->constrained('directions')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->string('fonction', 190)->nullable();
            $table->foreignId('statut_professionnel_id')->nullable()->constrained('nomenclatures')->nullOnDelete();
            $table->string('email_pro', 190)->nullable();
            $table->string('telephone_pro', 40)->nullable();
            $table->boolean('actif')->default(true);
            $table->date('date_desactivation')->nullable();
            $table->string('motif_desactivation', 255)->nullable();
            // Préparation d'une future intégration avec un SIRH existant
            $table->string('source', 30)->default('saisie'); // saisie | import | sirh
            $table->string('reference_externe', 100)->nullable()->index();
            $table->timestamps();
            $table->index(['nom', 'prenom']);
            $table->index(['service_id', 'actif']);
            $table->index(['direction_id', 'actif']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->foreignId('agent_id')->nullable()->unique()->constrained('agents')->nullOnDelete();
            // Service / direction de rattachement pour le cloisonnement des données
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('direction_id')->nullable()->constrained('directions')->nullOnDelete();
            $table->boolean('actif')->default(true)->index();
            $table->boolean('doit_changer_mot_de_passe')->default(false);
            $table->timestamp('mot_de_passe_modifie_le')->nullable();
            $table->timestamp('derniere_connexion_le')->nullable();
            $table->string('derniere_connexion_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->boolean('systeme')->default(false); // rôle initial fourni (supprimable seulement s'il n'est pas utilisé)
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('module', 50)->index();
            $table->string('libelle', 190);
            $table->boolean('sensible')->default(false);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('attribue_le')->nullable();
            $table->foreignId('attribue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('agents');
    }
};
