<?php

use App\Exceptions\RegleMetierException;
use App\Http\Middleware\EntetesSecurite;
use App\Http\Middleware\ExigerPermission;
use App\Http\Middleware\VerifierCompteActif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EntetesSecurite::class, VerifierCompteActif::class]);
        $middleware->alias(['permission' => ExigerPermission::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('accueil'));
        // Derrière un proxy inverse (HTTPS terminé en amont) : renseigner TRUSTED_PROXIES.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Règles métier : retour au formulaire avec un message compréhensible (gestion centralisée).
        $exceptions->render(function (RegleMetierException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('erreur', $e->getMessage());
        });
        $exceptions->dontReport([RegleMetierException::class]);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
