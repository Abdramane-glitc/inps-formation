<?php

namespace App\Models;

use App\Enums\StatutAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActionFormation extends Model
{
    use SoftDeletes; // corbeille (1.6.0)

    protected $table = 'actions_formation';

    public const TYPES = ['interne' => 'Interne', 'externe' => 'Externe', 'mixte' => 'Mixte (interne et externe)'];

    public const MODALITES = ['presentiel' => 'Présentiel', 'distanciel' => 'Distanciel', 'hybride' => 'Hybride'];

    /**
     * Une formation = cette action + sa session unique (depuis 1.3.0).
     * Référence, année, statut, demande d'origine et créateur sont gérés par le service métier.
     * Les colonnes plan_id et plan_action_code (plan annuel retiré en 1.3.0) et cout_previsionnel ainsi que la table depenses
     * (finances retirées en 1.4.0) sont conservées pour l'historique, sans usage.
     */
    protected $fillable = [
        'intitule', 'description', 'domaine_id', 'objectifs_pedagogiques', 'type', 'modalite',
        'organisme_id', 'public_cible',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutAction::class,
            'historique_importe' => 'boolean',
            'annee' => 'integer',
        ];
    }

    public function domaine(): BelongsTo
    {
        return $this->belongsTo(Domaine::class);
    }

    public function organisme(): BelongsTo
    {
        return $this->belongsTo(Organisme::class);
    }

    /** Demande de formation à l'origine de cette formation (facultatif). */
    /**
     * Cloisonnement (1.5.1) : la RH, l'autorité de validation et l'auditeur voient toutes les formations ;
     * un responsable de structure ne voit que celles demandées par son service ou suivies par au moins un agent de son service.
     */
    public static function voitToutesLesFormations(User $u): bool
    {
        return $u->aPermission('actions.gerer') || $u->aPermission('besoins.voir_tous') || $u->aPermission('agents.voir_tous');
    }

    /** Service auquel la vue est limitée, ou null si l'utilisateur voit tout. */
    public static function serviceLimite(User $u): ?int
    {
        return self::voitToutesLesFormations($u) ? null : (int) $u->service_id;
    }

    public function scopeVisiblesPar(Builder $q, User $u): Builder
    {
        if (self::voitToutesLesFormations($u)) {
            return $q;
        }
        if (! $u->aPermission('agents.voir_service') || ! $u->service_id) {
            return $q->whereRaw('1 = 0');
        }
        $sid = $u->service_id;

        return $q->where(fn ($w) => $w->whereHas('besoin', fn ($b) => $b->where('service_id', $sid))
            ->orWhereHas('sessions.inscriptions', fn ($i) => $i->whereIn('statut', SessionFormation::STATUTS_OCCUPANT_PLACE)
                ->whereHas('agent', fn ($a) => $a->where('service_id', $sid))));
    }

    public function estVisiblePar(User $u): bool
    {
        return self::voitToutesLesFormations($u) || self::whereKey($this->id)->visiblesPar($u)->exists();
    }

    /** Ligne du plan annuel que cette formation réalise (1.7.0). */
    public function lignePlan(): BelongsTo
    {
        return $this->belongsTo(PlanLigne::class, 'ligne_plan_id')->withTrashed();
    }

    public function besoin(): BelongsTo
    {
        return $this->belongsTo(BesoinFormation::class, 'besoin_id');
    }

    /** Période de la formation (dates, lieu, participants, présences). */
    public function session(): HasOne
    {
        return $this->hasOne(SessionFormation::class, 'action_id')->orderBy('date_debut');
    }

    /** Toutes les sessions (une seule depuis 1.3.0 ; relation conservée pour les calculs et l'historique importé). */
    public function sessions(): HasMany
    {
        return $this->hasMany(SessionFormation::class, 'action_id')->orderBy('date_debut');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
