<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Agent de l'INPS (référentiel du personnel). Jamais supprimé : désactivation logique.
 */
class Agent extends Model
{
    protected $fillable = [
        'matricule', 'nom', 'prenom', 'direction_id', 'service_id', 'fonction',
        'statut_professionnel_id', 'email_pro', 'telephone_pro', 'source', 'reference_externe',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'date_desactivation' => 'date'];
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function statutProfessionnel(): BelongsTo
    {
        return $this->belongsTo(Nomenclature::class, 'statut_professionnel_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /** Formations diplômantes (2.5.0). */
    public function formationsDiplomantes(): HasMany
    {
        return $this->hasMany(FormationDiplomante::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function getNomCompletAttribute(): string
    {
        return mb_strtoupper($this->nom).' '.$this->prenom;
    }

    /** Cloisonnement : restreint la requête au périmètre visible par l'utilisateur. */
    public function scopeVisiblesPar(Builder $q, User $user): Builder
    {
        if ($user->aPermission('agents.voir_tous')) {
            return $q;
        }
        if ($user->aPermission('agents.voir_service') && $user->service_id) {
            return $q->where('service_id', $user->service_id);
        }

        return $q->whereRaw('1 = 0');
    }

    public function scopeRecherche(Builder $q, ?string $terme): Builder
    {
        if (! $terme) {
            return $q;
        }
        $t = '%'.str_replace(['%', '_'], ['\%', '\_'], $terme).'%';

        return $q->where(fn ($w) => $w->where('matricule', 'like', $t)->orWhere('nom', 'like', $t)
            ->orWhere('prenom', 'like', $t)->orWhere('fonction', 'like', $t));
    }
}
