<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres institutionnels. Lecture via Parametre::valeur('cle', défaut) avec cache court.
 */
class Parametre extends Model
{
    protected $fillable = ['cle', 'valeur', 'type', 'groupe', 'libelle', 'aide', 'a_valider_inps'];

    protected function casts(): array
    {
        return ['a_valider_inps' => 'boolean'];
    }

    public const GROUPES = [
        'institution' => 'Identité institutionnelle',
        'regles' => 'Règles de présence, d\'achèvement et d\'attestation',
        'besoins' => 'Recensement des besoins',
        'notifications' => 'Notifications et échéances',
        'exports' => 'Paramètres d\'exportation',
        'conservation' => 'Durées de conservation',
    ];

    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        $tous = Cache::remember('parametres.tous', 300, fn () => static::query()->get(['cle', 'valeur', 'type'])
            ->mapWithKeys(fn ($p) => [$p->cle => [$p->valeur, $p->type]])->all());

        if (! array_key_exists($cle, $tous) || $tous[$cle][0] === null || $tous[$cle][0] === '') {
            return $defaut;
        }
        [$v, $type] = $tous[$cle];

        return match ($type) {
            'int' => (int) $v,
            'decimal' => (float) $v,
            'bool' => in_array($v, ['1', 'true', 'oui'], true),
            default => $v,
        };
    }

    public static function viderCache(): void
    {
        Cache::forget('parametres.tous');
    }
}
