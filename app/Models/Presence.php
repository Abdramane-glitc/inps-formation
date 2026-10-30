<?php

namespace App\Models;

use App\Enums\StatutPresence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Presence extends Model
{
    protected $fillable = [];

    protected $hidden = ['motif_absence'];

    protected function casts(): array
    {
        return ['statut' => StatutPresence::class];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(PresenceCorrection::class)->latest('id');
    }
}
