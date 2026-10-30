<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use App\Support\Profils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Accès des agents à l'application.
 *
 * Depuis la version 2.4.0 (plus simple que les feuilles d'accès de la 2.3) :
 *  - la RH génère un **code d'activation de 6 chiffres** pour chaque agent ; le code est affiché à l'écran
 *    (« Accès des agents ») tant qu'il n'a pas servi ; générer à nouveau ne le change pas (pas de piège du double clic) ;
 *  - l'agent ouvre « Première connexion ? Activer mon compte », saisit son matricule et son code, et choisit son mot de passe ;
 *  - le code sert une seule fois, expire au bout de JOURS_VALIDITE jours et est annulé après ESSAIS_MAX essais erronés ;
 *  - mot de passe oublié : « Nouveau code » (le mot de passe actuel reste valable tant que le nouveau code n'a pas servi).
 * Le code est chiffré en base (clé APP_KEY) : seule la RH le voit ; il n'est jamais écrit dans le journal.
 */
class AccesAgentService
{
    public const JOURS_VALIDITE = 15;

    public const ESSAIS_MAX = 5;

    /**
     * Prépare l'accès des agents : compte créé s'il n'existe pas, code d'activation attribué s'ils n'en ont pas de valable
     * (ou toujours un nouveau avec $nouveau = true). Un agent inactif est ignoré.
     *
     * @param  list<int>  $agentIds
     * @return array{comptes:int, codes:int}
     */
    public function genererCodes(array $agentIds, ?User $acteur = null, bool $nouveau = false): array
    {
        $role = Role::where('code', 'agent')->firstOrFail();
        $agents = Agent::whereIn('id', $agentIds)->where('actif', true)->with('user')->orderBy('nom')->get();
        $comptes = 0;
        $codes = 0;
        DB::transaction(function () use ($agents, $role, $acteur, $nouveau, &$comptes, &$codes) {
            foreach ($agents as $ag) {
                $u = $ag->user;
                if (! $u) {
                    $email = $ag->email_pro && filter_var($ag->email_pro, FILTER_VALIDATE_EMAIL) && ! User::where('email', $ag->email_pro)->exists() ? $ag->email_pro : null;
                    // Mot de passe aléatoire inconnu de tous : l'agent choisit le sien à l'activation
                    $u = new User(['name' => $ag->nom_complet, 'email' => $email, 'password' => Str::password(32), 'agent_id' => $ag->id,
                        'service_id' => $ag->service_id, 'direction_id' => $ag->direction_id]);
                    $u->actif = true;
                    $u->doit_changer_mot_de_passe = false;
                    $u->save();
                    $u->roles()->attach($role->id, ['attribue_le' => now(), 'attribue_par' => $acteur?->id]);
                    $comptes++;
                }
                if (! $u->actif && Profils::estAgent($u->loadMissing('roles'))) {
                    $u->actif = true; // agent de nouveau actif : son accès aussi
                }
                if ($nouveau || ! $u->aCodeValide()) {
                    $u->forceFill(['code_activation' => $this->code(), 'code_activation_expire_le' => now()->addDays(self::JOURS_VALIDITE)->endOfDay(),
                        'code_activation_essais' => 0])->save();
                    $codes++;
                } elseif ($u->isDirty()) {
                    $u->save();
                }
            }
        });
        if ($comptes || $codes) {
            Audit::enregistrer('utilisateur.codes_activation', 'user', "Accès des agents : {$comptes} compte(s) créé(s), {$codes} code(s) d'activation attribué(s)",
                ['matricules' => $agents->pluck('matricule')->all()]); // jamais les codes eux-mêmes
        }

        return ['comptes' => $comptes, 'codes' => $codes];
    }

    /**
     * Activation par l'agent : matricule + code → il choisit son mot de passe. Renvoie le compte activé.
     * Message volontairement identique pour toutes les erreurs (pas d'indice sur les matricules existants).
     */
    public function activer(string $matricule, string $code, string $motDePasse): User
    {
        $erreur = 'Matricule ou code incorrect, ou code expiré. Demandez un nouveau code à la Direction des ressources humaines.';
        $code = preg_replace('/\D/', '', $code); // « 482 913 » ou « 482913 »

        $u = DB::transaction(function () use ($matricule, $code, $motDePasse, $erreur) {
            $u = User::whereHas('agent', fn ($q) => $q->where('matricule', mb_strtoupper(trim($matricule))))->lockForUpdate()->first();
            if (! $u || ! $u->actif || ! $u->aCodeValide()) {
                throw new RegleMetierException($erreur);
            }
            if (! hash_equals((string) $u->code_activation, $code)) {
                // L'essai erroné est compté APRÈS la transaction : une exception ici annulerait le compteur
                return $u->id;
            }
            $u->forceFill(['password' => Hash::make($motDePasse), 'code_activation' => null, 'code_activation_expire_le' => null, 'code_activation_essais' => 0,
                'doit_changer_mot_de_passe' => false, 'mot_de_passe_modifie_le' => now(), 'active_le' => now(), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $u->id)->delete();
            Audit::enregistrer('securite.activation_compte', $u, 'Compte d\'agent activé avec un code', [], $u->id);

            return $u;
        });
        if (is_int($u)) {
            $essais = DB::transaction(function () use ($u) {
                User::whereKey($u)->increment('code_activation_essais');

                return (int) User::whereKey($u)->value('code_activation_essais');
            });
            if ($essais >= self::ESSAIS_MAX) {
                Audit::enregistrer('securite.code_activation_bloque', User::find($u), 'Code d\'activation annulé après '.self::ESSAIS_MAX.' essais erronés');
            }
            throw new RegleMetierException($erreur);
        }

        return $u;
    }

    /** 6 chiffres, sans suite trop évidente (111111, 123456, 654321…). */
    private function code(): string
    {
        do {
            $c = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (count(array_unique(str_split($c))) < 3 || str_contains('01234567890', $c) || str_contains('09876543210', $c));

        return $c;
    }
}
