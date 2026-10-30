<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLigne extends Model
{
    protected $table = 'import_lignes';

    protected $fillable = ['import_id', 'numero', 'donnees', 'statut', 'messages'];

    protected function casts(): array
    {
        return ['donnees' => 'array', 'messages' => 'array'];
    }

    public $timestamps = false;

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
