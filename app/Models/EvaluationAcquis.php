<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationAcquis extends Model
{
    protected $table = 'evaluations_acquis';

    protected $fillable = ['inscription_id', 'methode', 'score', 'score_max', 'reussi', 'resultat', 'appreciation', 'evaluateur_id'];

    /** Réponses des deux évaluations (2.2.0) : à chaud « a-t-il compris ? », à froid « applique-t-il ? ». */
    public const REPONSES = ['oui' => 'Oui', 'partiel' => 'En partie', 'non' => 'Non'];

    /** Note facultative à chaud : sur 20. */
    public const NOTE_MAX = 20;

    protected function casts(): array
    {
        return ['reussi' => 'boolean', 'score' => 'decimal:2', 'score_max' => 'decimal:2'];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }
}
