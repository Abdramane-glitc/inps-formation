<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionChangement extends Model
{
    protected $table = 'session_changements';

    protected $fillable = ['session_id', 'type', 'ancienne_date_debut', 'ancienne_date_fin', 'nouvelle_date_debut', 'nouvelle_date_fin', 'ancien_statut', 'nouveau_statut', 'motif', 'user_id'];

    protected function casts(): array
    {
        return ['ancienne_date_debut' => 'date', 'ancienne_date_fin' => 'date', 'nouvelle_date_debut' => 'date', 'nouvelle_date_fin' => 'date', 'created_at' => 'datetime'];
    }

    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
