<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refonte visuelle (v1.1) : bleu institutionnel INPS par défaut et emplacement de la photographie
 * du siège. Ne modifie la couleur que si elle vaut encore l'ancienne valeur provisoire.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametres')->where('cle', 'institution.couleur_principale')->where('valeur', '#0b5d4b')
            ->update(['valeur' => '#003B73', 'aide' => 'Bleu institutionnel INPS (#003B73) — à confirmer avec la charte graphique officielle.', 'updated_at' => now()]);

        if (! DB::table('parametres')->where('cle', 'institution.photo_connexion')->exists()) {
            DB::table('parametres')->insert([
                'cle' => 'institution.photo_connexion', 'valeur' => null, 'type' => 'file', 'groupe' => 'institution',
                'libelle' => 'Photographie du siège (page de connexion)',
                'aide' => 'Photographie officielle du siège de l\'INPS, format paysage, 1600 px de large minimum (JPG/PNG/WebP). Ne pas utiliser l\'image d\'un autre bâtiment.',
                'a_valider_inps' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('parametres')->where('cle', 'institution.photo_connexion')->delete();
    }
};
