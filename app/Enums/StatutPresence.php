<?php

namespace App\Enums;

/**
 * Statuts de présence à une séance.
 * Les codes sont stables (utilisés par la logique métier) ; les libellés sont affichés à l'écran.
 */
enum StatutPresence: string
{
    case Present = 'present';
    case Retard = 'retard';
    case Partiel = 'partiel';
    case Absent = 'absent';

    public function libelle(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Retard => 'En retard',
            self::Partiel => 'Participation partielle',
            self::Absent => 'Absent',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::Present => 'vert',
            self::Retard => 'orange',
            self::Partiel => 'orange',
            self::Absent => 'rouge',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        $o = [];
        foreach (self::cases() as $c) {
            $o[$c->value] = $c->libelle();
        }

        return $o;
    }
}
