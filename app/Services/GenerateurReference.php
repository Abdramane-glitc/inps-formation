<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Génère des références lisibles et uniques : PREFIXE-ANNEE-NNNN.
 * Le calcul s'appuie sur la plus grande référence existante, sous verrou (FOR UPDATE)
 * dans la transaction appelante ; la contrainte d'unicité en base garantit l'absence de doublon.
 */
class GenerateurReference
{
    public static function suivante(string $table, string $colonne, string $prefixe, int $annee): string
    {
        $base = $prefixe.'-'.$annee.'-';
        $derniere = DB::table($table)->where($colonne, 'like', $base.'%')->lockForUpdate()->orderByDesc($colonne)->value($colonne);
        $n = $derniere ? ((int) substr($derniere, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
