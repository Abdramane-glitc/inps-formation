<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seance extends Model
{
    protected $table = 'seances';

    protected $fillable = ['session_id', 'date', 'libelle', 'heure_debut', 'heure_fin'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionFormation::class, 'session_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }
}
