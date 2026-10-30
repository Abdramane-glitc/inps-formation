<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Domaine extends Model
{
    protected $table = 'domaines';

    protected $fillable = ['code', 'nom', 'description', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function scopeActifs(Builder $q): Builder
    {
        return $q->where('actif', true)->orderBy('nom');
    }
}
