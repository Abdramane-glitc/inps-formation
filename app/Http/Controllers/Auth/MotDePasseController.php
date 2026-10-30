<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Illuminate\View\View;
use Throwable;

/**
 * Mot de passe : réinitialisation par lien e-mail (broker natif Laravel, jeton à usage unique
 * et durée limitée) et changement par l'utilisateur connecté.
 * Sans serveur SMTP opérationnel, la réinitialisation se fait par l'administrateur (mot de passe
 * temporaire à changer à la première connexion) : l'application ne prétend jamais avoir envoyé un e-mail.
 */
class MotDePasseController extends Controller
{
    public function demande(): View
    {
        return view('auth.mot-de-passe-oublie', ['smtp' => ! in_array(config('mail.default'), ['log', 'array'], true)]);
    }

    public function envoyerLien(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);
        try {
            $statut = Password::sendResetLink(['email' => $request->input('email'), 'actif' => true]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('erreur', 'Le service de messagerie est indisponible : aucun e-mail n\'a pu être envoyé. Contactez l\'administrateur pour une réinitialisation.');
        }
        Audit::enregistrer('securite.demande_reinitialisation', null, 'Demande de réinitialisation du mot de passe', ['empreinte_identifiant' => substr(hash('sha256', mb_strtolower($request->input('email'))), 0, 16), 'resultat' => $statut]);

        // Réponse identique que le compte existe ou non (pas d'énumération des comptes).
        return back()->with('succes', 'Si un compte actif correspond à cette adresse, un lien de réinitialisation vient d\'être transmis au serveur de messagerie.');
    }

    public function formulaireReinitialisation(Request $request, string $token): View
    {
        return view('auth.reinitialisation', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reinitialiser(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegleMotDePasse::defaults()],
        ]);
        $statut = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $u, string $mdp) {
            $u->forceFill(['password' => Hash::make($mdp), 'remember_token' => Str::random(60), 'doit_changer_mot_de_passe' => false, 'mot_de_passe_modifie_le' => now()])->save();
            event(new PasswordReset($u));
            Audit::enregistrer('securite.reinitialisation_mot_de_passe', $u, 'Mot de passe réinitialisé par lien', [], $u->id);
        });

        return $statut === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('succes', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Lien de réinitialisation invalide ou expiré.']);
    }

    public function edit(): View
    {
        return view('auth.changer-mot-de-passe');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'mot_de_passe_actuel' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:mot_de_passe_actuel', RegleMotDePasse::defaults()],
        ]);
        $u = $request->user();
        $u->forceFill(['password' => Hash::make($request->input('password')), 'doit_changer_mot_de_passe' => false, 'mot_de_passe_modifie_le' => now()])->save();
        // Invalide les autres sessions ouvertes de l'utilisateur
        DB::table('sessions')->where('user_id', $u->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();
        Audit::enregistrer('securite.changement_mot_de_passe', $u, 'Changement du mot de passe');

        return redirect()->route('accueil')->with('succes', 'Mot de passe modifié.');
    }
}
