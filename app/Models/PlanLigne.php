<?php

namespace App\Models;

use App\Enums\StatutSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Formation prévue au plan annuel. Son état se déduit de la formation qui la réalise (actions_formation.ligne_plan_id) :
 * prévue (aucune formation), programmée, terminée ou annulée.
 */
class PlanLigne extends Model
{
    use SoftDeletes;

    protected $table = 'plan_annuel_lignes';

    protected $fillable = ['intitule', 'periode', 'service_id', 'nb_agents', 'type', 'organisme_id', 'observations'];

    /** code => libellé, dans l'ordre de l'année */
    public const PERIODES = [
        'T1' => '1er trimestre', 'M01' => 'Janvier', 'M02' => 'Février', 'M03' => 'Mars',
        'T2' => '2e trimestre', 'M04' => 'Avril', 'M05' => 'Mai', 'M06' => 'Juin',
        'T3' => '3e trimestre', 'M07' => 'Juillet', 'M08' => 'Août', 'M09' => 'Septembre',
        'T4' => '4e trimestre', 'M10' => 'Octobre', 'M11' => 'Novembre', 'M12' => 'Décembre',
        'annee' => 'Dans l\'année',
    ];

    public const ETATS = ['prevue' => 'Prévue', 'programmee' => 'Programmée', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanAnnuel::class, 'plan_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function organisme(): BelongsTo
    {
        return $this->belongsTo(Organisme::class);
    }

    public function besoin(): BelongsTo
    {
        return $this->belongsTo(BesoinFormation::class, 'besoin_id');
    }

    /** Formation qui réalise la ligne (la plus récente non supprimée). */
    public function formation(): HasOne
    {
        return $this->hasOne(ActionFormation::class, 'ligne_plan_id')->latestOfMany();
    }

    public function libellePeriode(): string
    {
        return self::PERIODES[$this->periode] ?? $this->periode;
    }

    /** Trimestre de rattachement pour l'affichage groupé : T1..T4, ou « annee ». */
    public function trimestre(): string
    {
        if (str_starts_with($this->periode, 'M')) {
            return 'T'.(int) ceil((int) substr($this->periode, 1) / 3);
        }

        return $this->periode;
    }

    public function etat(): string
    {
        $s = $this->formation?->session;

        return match (true) {
            ! $this->formation => 'prevue',
            $s?->statut === StatutSession::Terminee => 'terminee',
            $s?->statut === StatutSession::Annulee => 'annulee',
            default => 'programmee',
        };
    }
}
