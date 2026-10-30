<?php

use Illuminate\Support\Carbon;

/*
| Fonctions d'affichage (formats français, montants en FCFA).
*/

if (! function_exists('fcfa')) {
    function fcfa(float|int|string|null $montant, bool $unite = true): string
    {
        if ($montant === null || $montant === '') {
            return '—';
        }

        return number_format((float) $montant, 0, ',', "\u{202F}").($unite ? "\u{00A0}FCFA" : '');
    }
}

if (! function_exists('date_fr')) {
    function date_fr(DateTimeInterface|string|null $d, bool $heure = false): string
    {
        if (! $d) {
            return '—';
        }
        $c = $d instanceof DateTimeInterface ? Carbon::instance($d) : Carbon::parse($d);

        return $c->format($heure ? 'd/m/Y H:i' : 'd/m/Y');
    }
}

if (! function_exists('pourcent')) {
    function pourcent(float|int|null $v): string
    {
        return $v === null ? '—' : number_format((float) $v, 1, ',', ' ')."\u{00A0}%";
    }
}
