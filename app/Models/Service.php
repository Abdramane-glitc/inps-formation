<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = ['direction_id', 'code', 'nom', 'actif'];

    /** Services actifs regroupés par direction, pour les listes déroulantes : [nom direction => [id => nom service]]. */
    public static function optionsParDirection(): array
    {
        return self::actifs()->with('direction')->get()->sortBy(fn ($s) => [$s->direction?->nom, $s->nom])
            ->groupBy(fn ($s) => ($s->direction?->code ? $s->direction->code.' — ' : '').($s->direction?->nom ?? 'Sans direction'))
            ->map(fn ($g) => $g->pluck('nom', 'id')->all())->all();
    }

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function scopeActifs(Builder $q): Builder
    {
        return $q->where('actif', true)->orderBy('nom');
    }
}
