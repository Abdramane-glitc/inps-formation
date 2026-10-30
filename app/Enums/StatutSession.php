<?php

namespace App\Enums;

/**
 * Statuts d'une session de formation.
 * Les codes sont stables (utilisés par la logique métier) ; les libellés sont affichés à l'écran.
 */
enum StatutSession: string
{
    case Planifiee = 'planifiee';
    case Confirmee = 'confirmee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Reportee = 'reportee';
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::Planifiee => 'Programmée',
            self::Confirmee => 'Confirmée',
            self::EnCours => 'En cours',
            self::Terminee => 'Terminée',
            self::Reportee => 'Reportée',
            self::Annulee => 'Annulée',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::Planifiee => 'bleu',
            self::Confirmee => 'indigo',
            self::EnCours => 'indigo',
            self::Terminee => 'vert',
            self::Reportee => 'orange',
            self::Annulee => 'rouge',
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
