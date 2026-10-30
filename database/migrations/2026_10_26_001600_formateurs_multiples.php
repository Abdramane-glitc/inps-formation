<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1.8.0 — une formation peut avoir plusieurs formateurs, internes et externes (type « mixte »).
 * Le formateur unique de chaque session (formateur_id) est recopié dans la nouvelle table de liaison ;
 * la colonne est conservée (lecture seule) pour ne rien perdre de l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_formateur', function (Blueprint $t) {
            $t->id();
            $t->foreignId('session_id')->constrained('sessions_formation')->cascadeOnDelete();
            $t->foreignId('formateur_id')->constrained('formateurs')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['session_id', 'formateur_id']);
        });
        $maintenant = now();
        DB::table('sessions_formation')->whereNotNull('formateur_id')->orderBy('id')->each(function ($s) use ($maintenant) {
            DB::table('session_formateur')->insert(['session_id' => $s->id, 'formateur_id' => $s->formateur_id, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_formateur');
    }
};
