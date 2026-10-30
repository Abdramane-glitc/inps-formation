<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Décision tracée (auteur, date, motif) sur une demande de formation. Non modifiable. */
class Decision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['action', 'statut_avant', 'statut_apres', 'etape_ordre', 'commentaire', 'user_id'];

    public const LIBELLES = [
        'creation' => 'Création',
        'soumission' => 'Envoi à la RH',
        'mise_en_examen' => 'Mise en examen',
        'demande_complement' => 'Demande de précisions',
        'acceptation' => 'Acceptation',
        'rejet' => 'Refus',
        'organisation' => 'Formation programmée',
        'inscription_plan' => 'Acceptée et inscrite au plan annuel',
        'formation_supprimee' => 'Formation supprimée',
        'suppression' => 'Demande supprimée',
        'restauration' => 'Demande restaurée',
        // Libellés conservés pour l'historique des versions antérieures à 1.3.0 (plan annuel supprimé)
        'integration_plan' => 'Intégration au plan',
        'retrait_plan' => 'Retrait du plan',
        'validation_etape' => 'Validation d\'étape',
        'rejet_etape' => 'Rejet à une étape',
        'validation_finale' => 'Validation finale (verrouillage)',
        'revision' => 'Ouverture d\'une révision',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function decidable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLibelleAttribute(): string
    {
        return self::LIBELLES[$this->action] ?? $this->action;
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Une décision enregistrée ne peut pas être modifiée.'));
        static::deleting(fn () => throw new \LogicException('Une décision enregistrée ne peut pas être supprimée.'));
    }
}
