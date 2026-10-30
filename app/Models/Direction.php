<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direction extends Model
{
    protected $table = 'directions';

    protected $fillable = ['code', 'nom', 'sigle', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function scopeActives(Builder $q): Builder
    {
        return $q->where('actif', true)->orderBy('nom');
    }
}
