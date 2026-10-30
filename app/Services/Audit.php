<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Point d'entrée unique du journal d'audit.
 * Ne jamais transmettre de secret ni de donnée personnelle superflue dans $contexte :
 * les clés sensibles connues sont retirées automatiquement par sécurité.
 */
class Audit
{
    private const CLES_INTERDITES = ['password', 'mot_de_passe', 'password_confirmation', 'token', 'remember_token', '_token', 'motif_absence', 'secret'];

    public static function enregistrer(string $action, Model|string|null $objet = null, ?string $description = null, array $contexte = [], ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $type = $objet instanceof Model ? self::type($objet) : $objet;
        $id = $objet instanceof Model ? $objet->getKey() : null;

        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'objet_type' => $type,
            'objet_id' => $id,
            'description' => $description ? mb_substr($description, 0, 500) : null,
            'contexte' => $contexte ? self::nettoyer($contexte) : null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : 'console',
        ]);
    }

    /** Type stable de l'objet ; un modèle absent de la table des types ne doit jamais faire échouer l'action journalisée. */
    private static function type(Model $m): string
    {
        try {
            return $m->getMorphClass();
        } catch (ClassMorphViolationException) {
            return Str::snake(class_basename($m));
        }
    }

    private static function nettoyer(array $c): array
    {
        $c = Arr::except($c, self::CLES_INTERDITES);
        foreach ($c as $k => $v) {
            if (is_array($v)) {
                $c[$k] = self::nettoyer($v);
            }
        }

        return $c;
    }

    /** Différences avant/après limitées aux champs listés (pour les modifications financières, etc.). */
    public static function differences(Model $m, array $champs): array
    {
        $diff = [];
        foreach ($champs as $ch) {
            if ($m->wasChanged($ch) || $m->isDirty($ch)) {
                $avant = $m->getOriginal($ch);
                $diff[$ch] = ['avant' => $avant instanceof \BackedEnum ? $avant->value : (string) $avant, 'apres' => (string) ($m->getAttributes()[$ch] ?? '')];
            }
        }

        return $diff;
    }
}
