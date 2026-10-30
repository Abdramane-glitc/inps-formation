<?php

namespace App\Enums;

/**
 * Statuts d'une demande de formation (parcours simplifié, version 1.4.0) :
 * envoyée → acceptée (formation programmée en même temps) / refusée / à compléter.
 * Les codes sont stables (utilisés par la logique métier) ; les libellés sont affichés à l'écran.
 * « Brouillon » ne concerne que des demandes enregistrées avant la version 1.4.0 (une demande est désormais envoyée directement).
 */
enum StatutBesoin: string
{
    case Brouillon = 'brouillon';
    case Soumise = 'soumise';
    case ACompleter = 'a_completer';
    case Acceptee = 'acceptee';
    case Rejetee = 'rejetee';
    case AuPlan = 'au_plan'; // 1.7.0 : acceptée et inscrite au plan annuel, à programmer
    case Organisee = 'organisee';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Non envoyée',
            self::Soumise => 'Envoyée',
            self::ACompleter => 'À compléter',
            self::Acceptee => 'Acceptée',
            self::Rejetee => 'Refusée',
            self::AuPlan => 'Inscrite au plan',
            self::Organisee => 'Formation programmée',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::Brouillon => 'gris',
            self::Soumise => 'bleu',
            self::ACompleter => 'orange',
            self::Acceptee => 'vert',
            self::Rejetee => 'rouge',
            self::AuPlan => 'gris',
            self::Organisee => 'indigo',
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
