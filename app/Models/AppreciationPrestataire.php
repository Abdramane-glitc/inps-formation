<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppreciationPrestataire extends Model
{
    protected $table = 'appreciations_prestataires';

    protected $fillable = ['organisme_id', 'action_id', 'note', 'commentaire', 'user_id'];

    protected function casts(): array
    {
        return [];
    }

    public function organisme(): BelongsTo
    {
        return $this->belongsTo(Organisme::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ActionFormation::class, 'action_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
