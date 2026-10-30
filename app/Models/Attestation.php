<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Attestation extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return ['emise_le' => 'datetime', 'annulee_le' => 'datetime'];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function emetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emise_par');
    }

    public function estValide(): bool
    {
        return $this->annulee_le === null;
    }

    /** Actions du journal d'audit qui correspondent à un téléchargement du PDF (donc à une impression possible). */
    public const ACTIONS_TELECHARGEMENT = ['attestation.telechargement', 'attestation.telechargement_agent'];

    /**
     * Nombre d'impressions de chaque attestation (28/10/2026), d'après le journal d'audit : chaque téléchargement du PDF
     * y est inscrit (l'application ne peut pas savoir si la feuille est réellement imprimée).
     *
     * @param  iterable<int>  $ids
     * @return array<int, array{nombre:int, derniere:Carbon}>
     */
    public static function impressions(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('audit_logs')->where('objet_type', 'attestation')->whereIn('objet_id', $ids)->whereIn('action', self::ACTIONS_TELECHARGEMENT)
            ->groupBy('objet_id')->select('objet_id', DB::raw('COUNT(*) as nombre'), DB::raw('MAX(created_at) as derniere'))->get()
            ->mapWithKeys(fn ($l) => [(int) $l->objet_id => ['nombre' => (int) $l->nombre, 'derniere' => Carbon::parse($l->derniere)]])->all();
    }
}
