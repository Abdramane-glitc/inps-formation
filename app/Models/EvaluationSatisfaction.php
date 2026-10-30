<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationSatisfaction extends Model
{
    protected $table = 'evaluations_satisfaction';

    protected $fillable = ['inscription_id', 'note_globale', 'note_contenu', 'note_formateur', 'note_organisation', 'note_utilite', 'commentaire', 'suggestions', 'saisi_par'];

    /** Critères de la fiche d'évaluation à chaud, notés de 1 (très insuffisant) à 5 (très satisfaisant) (1.9.0). */
    public const CRITERES = [
        'note_contenu' => 'Contenu de la formation',
        'note_formateur' => 'Qualité du ou des formateurs',
        'note_organisation' => 'Organisation (lieu, horaires, documents)',
        'note_utilite' => 'Utilité pour votre travail',
        'note_globale' => 'Appréciation générale',
    ];

    public const ECHELLE = [1 => 'Très insuffisant', 2 => 'Insuffisant', 3 => 'Moyen', 4 => 'Satisfaisant', 5 => 'Très satisfaisant'];

    protected function casts(): array
    {
        return [];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }
}
