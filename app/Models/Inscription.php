<?php

namespace App\Models;

use App\Enums\StatutInscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inscription extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'statut' => StatutInscription::class,
            'convoque_le' => 'datetime',
            'confirme_le' => 'datetime',
            'desiste_le' => 'datetime',
            'resultat_calcule_le' => 'datetime',
            'taux_presence' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionFormation::class, 'session_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(InscriptionEvenement::class)->latest('id');
    }

    public function evaluationSatisfaction(): HasOne
    {
        return $this->hasOne(EvaluationSatisfaction::class);
    }

    /** Évaluation à chaud (2.2.0) : le participant a-t-il compris la formation ? */
    public function evaluationAcquis(): HasOne
    {
        return $this->hasOne(EvaluationAcquis::class);
    }

    public function evaluationsImpact(): HasMany
    {
        return $this->hasMany(EvaluationImpact::class)->latest('date_evaluation');
    }

    public function attestation(): HasOne
    {
        return $this->hasOne(Attestation::class);
    }

    public function remplacant(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'remplace_par_id');
    }

    public function occupePlace(): bool
    {
        return in_array($this->statut->value, SessionFormation::STATUTS_OCCUPANT_PLACE, true);
    }
}
