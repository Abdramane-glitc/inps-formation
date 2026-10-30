<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit en ajout seul. Les modifications/suppressions sont refusées au niveau
 * applicatif (ci-dessous) et au niveau base de données (déclencheurs MySQL).
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'objet_type', 'objet_id', 'description', 'contexte', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['contexte' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Le journal d\'audit n\'est pas modifiable.'));
        static::deleting(fn () => throw new \LogicException('Le journal d\'audit n\'est pas supprimable.'));
    }
}
