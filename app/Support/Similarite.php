<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Rapprochement simple de deux intitulés de formation (2.1.0) : mots significatifs en commun.
 * Volontairement prudent : deux mots communs, ou un seul si l'un des intitulés n'en compte qu'un ou deux.
 */
final class Similarite
{
    /** Mots trop généraux pour rapprocher deux formations. */
    private const VIDES = ['formation', 'formations', 'initiation', 'perfectionnement', 'atelier', 'module', 'niveau', 'avance', 'avancee',
        'base', 'bases', 'pour', 'dans', 'avec', 'sans', 'des', 'les', 'aux', 'une', 'sur', 'par', 'demo', 'session', 'stage', 'cours',
        'techniques', 'technique', 'outils', 'outil', 'pratique', 'pratiques', 'agents', 'service', 'suivi', 'gestion', 'maitrise', 'actualisation'];

    /** @return list<string> */
    public static function mots(string $texte): array
    {
        $mots = preg_split('/[^a-z0-9]+/', Str::of($texte)->lower()->ascii()->toString(), -1, PREG_SPLIT_NO_EMPTY);
        $mots = array_map(fn ($m) => strlen($m) > 5 ? rtrim($m, 's') : $m, $mots); // pluriels simples

        return array_values(array_unique(array_filter($mots, fn ($m) => strlen($m) >= 4 && ! in_array($m, self::VIDES, true))));
    }

    public static function proches(string $a, string $b): bool
    {
        $ma = self::mots($a);
        $mb = self::mots($b);
        if ($ma === [] || $mb === []) {
            return false;
        }
        $communs = count(array_intersect($ma, $mb));

        return $communs >= 2 || ($communs >= 1 && min(count($ma), count($mb)) <= 2);
    }
}
