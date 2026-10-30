<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresenceCorrection extends Model
{
    protected $table = 'presence_corrections';

    protected $fillable = ['presence_id', 'ancien_statut', 'nouveau_statut', 'motif', 'user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function presence(): BelongsTo
    {
        return $this->belongsTo(Presence::class);
    }
}
