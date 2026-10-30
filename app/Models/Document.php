<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document stocké sur le disque privé. Jamais servi par URL publique :
 * le téléchargement passe par DocumentController@telecharger (autorisation + audit).
 */
class Document extends Model
{
    use SoftDeletes;

    protected $fillable = ['titre', 'categorie_id', 'confidentiel'];

    protected $hidden = ['chemin', 'sha256'];

    /** Types d'objets auxquels un document peut être rattaché (alias => classe). */
    public const RATTACHEMENTS = [
        'besoin' => BesoinFormation::class,
        'action' => ActionFormation::class,
        'session' => SessionFormation::class,
        'organisme' => Organisme::class,
        'diplomante' => FormationDiplomante::class,
    ];

    protected function casts(): array
    {
        return ['confidentiel' => 'boolean', 'taille' => 'integer'];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Nomenclature::class, 'categorie_id');
    }

    public function deposant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }

    public function tailleLisible(): string
    {
        $t = $this->taille;

        return $t >= 1048576 ? number_format($t / 1048576, 1, ',', ' ').' Mo' : number_format(max(1, $t / 1024), 0, ',', ' ').' Ko';
    }
}
