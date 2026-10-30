<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Plan annuel de formation (1.7.0) : une liste de formations prévues pour l'année, validée en un clic. */
class PlanAnnuel extends Model
{
    protected $table = 'plans_annuels';

    protected $fillable = ['annee'];

    protected function casts(): array
    {
        return ['valide_le' => 'datetime', 'annee' => 'integer'];
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(PlanLigne::class, 'plan_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function estValide(): bool
    {
        return $this->valide_le !== null;
    }
}
