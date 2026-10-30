<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** En-têtes HTTP de sécurité (CSP stricte : aucun script ni style externe). */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $vite = app()->environment('local') && is_file(public_path('hot')) ? ' '.trim((string) file_get_contents(public_path('hot'))).' ws:' : '';
        $h->set('Content-Security-Policy', "default-src 'self'; script-src 'self'$vite; style-src 'self' 'unsafe-inline'$vite; img-src 'self' data:; font-src 'self'; connect-src 'self'$vite; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'");
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'same-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (config('inps.forcer_https')) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($request->user() && ! $h->has('Content-Disposition')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
