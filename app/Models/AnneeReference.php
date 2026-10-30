<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnneeReference extends Model
{
    protected $table = 'annees_reference';

    protected $fillable = ['annee', 'statut', 'date_limite_besoins', 'date_cloture'];

    protected function casts(): array
    {
        return ['annee' => 'integer', 'date_limite_besoins' => 'date', 'date_cloture' => 'date'];
    }

    public function estOuverte(): bool
    {
        return $this->statut === 'ouverte';
    }

    public static function ouvertes(): array
    {
        return static::where('statut', 'ouverte')->orderByDesc('annee')->pluck('annee')->all();
    }
}
