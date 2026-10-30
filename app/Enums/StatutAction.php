<?php

namespace App\Enums;

/**
 * Statuts de réalisation d'une action de formation.
 * Les codes sont stables (utilisés par la logique métier) ; les libellés sont affichés à l'écran.
 */
enum StatutAction: string
{
    case Planifiee = 'planifiee';
    case EnCours = 'en_cours';
    case Realisee = 'realisee';
    case Reportee = 'reportee';
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::Planifiee => 'Programmée',
            self::EnCours => 'En cours',
            self::Realisee => 'Réalisée',
            self::Reportee => 'Reportée',
            self::Annulee => 'Annulée',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::Planifiee => 'bleu',
            self::EnCours => 'indigo',
            self::Realisee => 'vert',
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
