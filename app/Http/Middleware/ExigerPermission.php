<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'accès côté serveur : route accessible si l'utilisateur détient AU MOINS UNE
 * des permissions listées (séparées par « | »). Refus par défaut.
 * Exemple : ->middleware('permission:besoins.voir_tous|besoins.voir_service')
 */
class ExigerPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        if (! $user || ! $user->aUneDesPermissions(...explode('|', $permissions))) {
            abort(403, 'Vous ne disposez pas des droits nécessaires pour accéder à cette page.');
        }

        return $next($request);
    }
}
