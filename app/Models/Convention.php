<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convention extends Model
{
    protected $table = 'conventions';

    protected $fillable = ['organisme_id', 'reference', 'objet', 'date_debut', 'date_fin']; // montant : plus saisi depuis 1.4.0

    protected function casts(): array
    {
        return ['date_debut' => 'date', 'date_fin' => 'date'];
    }

    public function organisme(): BelongsTo
    {
        return $this->belongsTo(Organisme::class);
    }
}
