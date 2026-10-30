<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Attestation d'intervention d'un formateur externe (1.9.0) : une par formateur et par formation. */
class AttestationIntervenant extends Model
{
    protected $table = 'attestations_intervenants';

    protected $fillable = [];

    protected function casts(): array
    {
        return ['emise_le' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionFormation::class, 'session_id');
    }

    public function formateur(): BelongsTo
    {
        return $this->belongsTo(Formateur::class);
    }

    public function emetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emise_par');
    }
}
