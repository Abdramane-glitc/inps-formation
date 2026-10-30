<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2.2.0 — évaluations simplifiées (demande de l'INPS du 27/10/2026), même modèle des deux côtés :
 *  - à chaud (RH, fin de formation) : « le participant a-t-il compris ? » oui / en partie / non (+ note sur 20 facultative)
 *    → table evaluations_acquis, nouvelle colonne « resultat » ;
 *  - à froid (chef de service, 3 à 6 mois après) : « applique-t-il ce qu'il a appris ? » oui / en partie / non
 *    → table evaluations_impact, colonne « resultat_atteint » ; le niveau 1 à 4 devient facultatif.
 * L'avis des participants (evaluations_satisfaction) n'est plus saisi ; les fiches existantes sont conservées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations_acquis', function (Blueprint $t) {
            $t->string('resultat', 10)->nullable()->after('reussi'); // oui | partiel | non
        });
        DB::table('evaluations_acquis')->whereNull('resultat')->update(['resultat' => DB::raw("CASE WHEN reussi THEN 'oui' ELSE 'non' END")]);
        Schema::table('evaluations_impact', function (Blueprint $t) {
            $t->unsignedTinyInteger('niveau_mise_en_pratique')->nullable()->change();
        });
        DB::table('evaluations_impact')->whereNull('resultat_atteint')->whereNotNull('niveau_mise_en_pratique')
            ->update(['resultat_atteint' => DB::raw("CASE WHEN niveau_mise_en_pratique >= 3 THEN 'oui' WHEN niveau_mise_en_pratique = 2 THEN 'partiel' ELSE 'non' END")]);
        DB::table('permissions')->where('code', 'evaluations.gerer')->update(['libelle' => 'Vérifier en fin de formation que les participants ont compris, et suivre les évaluations à 3–6 mois', 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'evaluations.froid')->update(['libelle' => 'Indiquer, 3 à 6 mois après, si les agents de son service appliquent ce qu\'ils ont appris', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('evaluations_acquis', fn (Blueprint $t) => $t->dropColumn('resultat'));
    }
};
