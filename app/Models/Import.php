<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    protected $table = 'imports';

    protected $fillable = ['type', 'nom_fichier', 'statut', 'total_lignes', 'lignes_valides', 'lignes_erreur', 'lignes_doublon', 'lignes_importees', 'message', 'user_id', 'termine_le'];

    protected function casts(): array
    {
        return ['termine_le' => 'datetime'];
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(ImportLigne::class)->orderBy('numero');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
