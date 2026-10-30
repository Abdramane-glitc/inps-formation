<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationImpact extends Model
{
    protected $table = 'evaluations_impact';

    protected $fillable = ['inscription_id', 'date_evaluation', 'niveau_mise_en_pratique', 'resultat_atteint', 'competences_observees', 'commentaire', 'evaluateur_id'];

    /** Le résultat attendu (indiqué dans la demande) est-il atteint ? (1.9.0) */
    public const RESULTATS = ['oui' => 'Oui', 'partiel' => 'En partie', 'non' => 'Non'];

    protected function casts(): array
    {
        return ['date_evaluation' => 'date'];
    }

    public const NIVEAUX = [1 => 'Non mise en pratique', 2 => 'Mise en pratique partielle', 3 => 'Mise en pratique satisfaisante', 4 => 'Maîtrise et transmission'];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function evaluateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluateur_id');
    }
}
