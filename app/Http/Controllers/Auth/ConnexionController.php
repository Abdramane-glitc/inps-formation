<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Connexion par e-mail (ou par matricule pour un compte d'agent, 2.3.0) et mot de passe.
 * Limitation des tentatives : middleware « throttle:connexion » (AppServiceProvider).
 */
class ConnexionController extends Controller
{
    /** Empreinte bcrypt d'un secret aléatoire jeté : sert seulement à égaliser la durée de vérification. */
    private const EMPREINTE_FACTICE = '$2y$12$aW0teo0dmCBe7zArmsvwLeq8stU.pWMtuwbdw7J/Pvs3FRg6EP/Za';

    public function create(): View
    {
        return view('auth.connexion');
    }

    public function store(Request $request): RedirectResponse
    {
        $cred = $request->validate([
            'email' => ['required', 'string', 'max:190'], // e-mail, ou matricule pour un agent (2.3.0)
            'password' => ['required', 'string', 'max:255'],
        ], [], ['email' => 'e-mail ou matricule']);
        $identifiant = trim($cred['email']);
        $empreinte = substr(hash('sha256', mb_strtolower($identifiant)), 0, 16); // aucun identifiant en clair dans le journal
        $existant = str_contains($identifiant, '@')
            ? User::where('email', $identifiant)->first()
            : User::whereHas('agent', fn ($q) => $q->where('matricule', mb_strtoupper($identifiant)))->first();

        // Vérification du mot de passe même si le compte n'existe pas : même durée de réponse (pas d'indice sur les identifiants)
        $motDePasseValide = Hash::check($cred['password'], $existant?->password ?? self::EMPREINTE_FACTICE);
        if (! $existant || ! $existant->actif || ! $motDePasseValide) {
            Audit::enregistrer('securite.connexion_echec', null, 'Échec de connexion', ['empreinte_identifiant' => $empreinte, 'compte_inactif' => (bool) ($existant && ! $existant->actif)], $existant?->id);

            // Agent qui n'a pas encore activé son compte (2.4.0) : on l'oriente vers l'activation par code
            if ($existant && $existant->actif && ! $motDePasseValide && $existant->aCodeValide()) {
                return redirect()->route('activation')->withInput(['matricule' => $identifiant])
                    ->with('avertissement', 'Votre compte n\'est pas encore activé : saisissez le code à 6 chiffres donné par la RH et choisissez votre mot de passe.');
            }

            return back()->withInput($request->only('email'))->withErrors(['email' => 'Identifiants incorrects ou compte désactivé.']);
        }
        Auth::login($existant);
        $request->session()->regenerate(); // protection contre la fixation de session
        $u = $request->user();
        $u->forceFill(['derniere_connexion_le' => now(), 'derniere_connexion_ip' => $request->ip()])->save();
        Audit::enregistrer('securite.connexion', $u, 'Connexion réussie');

        return redirect()->intended(route('accueil'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::enregistrer('securite.deconnexion', $request->user(), 'Déconnexion');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
