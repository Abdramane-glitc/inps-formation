<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\RegleMetierException;
use App\Http\Controllers\Controller;
use App\Services\AccesAgentService;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** « Première connexion ? Activer mon compte » (2.4.0) : matricule + code à 6 chiffres + choix du mot de passe. */
class ActivationController extends Controller
{
    public function create(): View
    {
        return view('auth.activation');
    }

    public function store(Request $r, AccesAgentService $acces): RedirectResponse
    {
        $d = $r->validate([
            'matricule' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:12'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['matricule' => 'matricule', 'code' => 'code d\'activation', 'password' => 'mot de passe']);
        try {
            $u = $acces->activer($d['matricule'], $d['code'], $d['password']);
        } catch (RegleMetierException $e) {
            // Ni le code ni le mot de passe ne sont renvoyés dans le formulaire
            return back()->withInput($r->only('matricule'))->withErrors(['code' => $e->getMessage()]);
        }
        Auth::login($u);
        $r->session()->regenerate();
        $u->forceFill(['derniere_connexion_le' => now(), 'derniere_connexion_ip' => $r->ip()])->save();
        Audit::enregistrer('securite.connexion', $u, 'Connexion réussie (activation du compte)');

        return redirect()->route('espace.index')->with('succes', 'Votre compte est activé. Désormais, connectez-vous avec votre matricule et votre mot de passe.');
    }
}
