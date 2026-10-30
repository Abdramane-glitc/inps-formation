<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactOrganisme extends Model
{
    protected $table = 'contacts_organisme';

    protected $fillable = ['organisme_id', 'nom', 'fonction', 'telephone', 'email'];

    protected function casts(): array
    {
        return [];
    }

    public function organisme(): BelongsTo
    {
        return $this->belongsTo(Organisme::class);
    }
}
