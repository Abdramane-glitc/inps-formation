<?php

namespace App\Enums;

/**
 * Statuts d'une inscription.
 * Les codes sont stables (utilisés par la logique métier) ; les libellés sont affichés à l'écran.
 */
enum StatutInscription: string
{
    case Inscrit = 'inscrit';
    case ListeAttente = 'liste_attente';
    case Convoque = 'convoque';
    case Confirme = 'confirme';
    case Desiste = 'desiste';
    case Remplace = 'remplace';
    case Annule = 'annule';

    public function libelle(): string
    {
        return match ($this) {
            self::Inscrit => 'Inscrit',
            self::ListeAttente => "Liste d'attente",
            self::Convoque => 'Convoqué',
            self::Confirme => 'Confirmé',
            self::Desiste => 'Désisté',
            self::Remplace => 'Remplacé',
            self::Annule => 'Annulé',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::Inscrit => 'bleu',
            self::ListeAttente => 'orange',
            self::Convoque => 'indigo',
            self::Confirme => 'vert',
            self::Desiste => 'gris',
            self::Remplace => 'gris',
            self::Annule => 'rouge',
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
