<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Nomenclature extends Model
{
    protected $table = 'nomenclatures';

    protected $fillable = ['type', 'code', 'libelle', 'ordre', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'ordre' => 'integer'];
    }

    /** Types de nomenclatures paramétrables et leur libellé. */
    public const TYPES = [
        'statut_professionnel' => 'Statuts professionnels',
        'categorie_document' => 'Catégories de documents',
        // Les listes « priorités », « types de formation », « axes du plan » et « catégories de dépenses »
        // ne sont plus utilisées depuis la version 1.4.0 ; leurs valeurs restent en base pour l'historique.
    ];

    public function scopeDeType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type)->where('actif', true)->orderBy('ordre')->orderBy('libelle');
    }
}
