<?php

namespace App\Http\Middleware;

use App\Services\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Déconnecte immédiatement un compte désactivé et impose le changement de mot de passe si requis. */
class VerifierCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->actif) {
            Audit::enregistrer('securite.session_compte_inactif', $user, 'Session interrompue : compte désactivé');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Votre compte est désactivé. Contactez l\'administrateur.']);
        }
        if ($user && $user->doit_changer_mot_de_passe && ! $request->routeIs('mot-de-passe.*', 'logout')) {
            return redirect()->route('mot-de-passe.edit')->with('avertissement', 'Vous devez définir un nouveau mot de passe avant de continuer.');
        }

        return $next($request);
    }
}
