<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\RegleMetierException;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit;
use App\Support\CataloguePermissions;
use App\Support\Profils;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestion des comptes, avec deux profils fixes (RH, Responsable de structure — voir App\Support\Profils). Garde-fous contre l'élévation de privilèges :
 *  - un utilisateur ne modifie jamais ses propres rôles ni ne désactive son propre compte ;
 *  - les rôles d'administration (gestion des comptes/rôles, supervision) ne peuvent être attribués
 *    que par un détenteur de « roles.gerer » ;
 *  - le dernier administrateur technique actif ne peut être ni désactivé ni privé de ce rôle.
 */
class UtilisateurController extends Controller
{
    private const PERMISSIONS_ADMIN = ['roles.gerer', 'utilisateurs.gerer', 'systeme.superviser'];

    public function index(Request $r): View
    {
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'profil' => ['nullable', Rule::in([...array_keys(Profils::PROFILS), 'agent'])], 'actif' => ['nullable', 'in:0,1']]);
        $roleProfil = isset($f['profil']) ? ($f['profil'] === 'agent' ? 'agent' : Profils::PROFILS[$f['profil']][1][0]) : null;
        // Les comptes d'agents (2.3.0) se gèrent depuis Personnel › Accès des agents : masqués sauf filtre « Agent »
        $sansAgents = fn ($q) => $q->where(fn ($w) => $w->whereDoesntHave('roles')->orWhereHas('roles', fn ($r) => $r->where('code', '!=', 'agent')));

        return view('admin.utilisateurs.index', ['users' => User::with(['roles', 'service', 'agent'])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($v, '%_').'%')->orWhere('email', 'like', '%'.addcslashes($v, '%_').'%')))
            ->when($roleProfil, fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('code', $v)))
            ->when($roleProfil !== 'agent', $sansAgents)
            ->when(isset($f['actif']), fn ($q) => $q->where('actif', $f['actif'] === '1'))
            ->orderBy('name')->paginate(25)->withQueryString(), 'f' => $f]);
    }

    public function create(): View
    {
        return view('admin.utilisateurs.form', ['u' => new User] + $this->listes());
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $this->valider($r);
        $d['roles'] = Profils::roleIds($d['profil']);
        $this->verifierRolesAttribuables($r->user(), $d['roles']);
        $mdp = $this->motDePasseTemporaire();
        $u = DB::transaction(function () use ($d, $mdp, $r) {
            $u = new User(['name' => $d['name'], 'email' => $d['email'], 'password' => $mdp,
                'service_id' => $d['service_id'] ?? null, 'direction_id' => $this->directionDuService($d['service_id'] ?? null)]);
            $u->actif = true;
            $u->doit_changer_mot_de_passe = true;
            $u->save();
            $this->synchroniserRoles($u, $d['roles'] ?? [], $r->user());
            $this->appliquerDroits($u, $this->droitsDemandes($d), $r->user());
            Audit::enregistrer('utilisateur.creation', $u, "Création du compte {$u->identifiant}", ['roles' => $u->roles()->pluck('code')->all()]);

            return $u;
        });

        return redirect()->route('admin.utilisateurs.edit', $u)->with('mot_de_passe_temporaire', $mdp)
            ->with('succes', 'Compte créé. Communiquez le mot de passe temporaire par un canal sûr : il ne sera plus affiché.');
    }

    public function edit(User $utilisateur): View
    {
        return view('admin.utilisateurs.form', ['u' => $utilisateur->load('roles')] + $this->listes());
    }

    public function update(Request $r, User $utilisateur): RedirectResponse
    {
        if (Profils::estAgent($utilisateur->loadMissing('roles'))) {
            return back()->with('erreur', 'Le compte d\'un agent se gère depuis Personnel › Accès des agents (mot de passe) et la fiche de l\'agent.');
        }
        $d = $this->valider($r, $utilisateur);
        $soiMeme = $utilisateur->id === $r->user()->id;
        $actuels = $utilisateur->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
        // « conserver » : compte créé avant la version 1.6.0 avec une autre combinaison de rôles, laissée telle quelle
        $nouveaux = $d['profil'] === 'conserver' ? $actuels : Profils::roleIds($d['profil']);
        sort($nouveaux);
        sort($actuels);
        if ($soiMeme && $nouveaux !== $actuels) {
            return back()->with('erreur', 'Vous ne pouvez pas modifier votre propre profil.');
        }
        $this->verifierRolesAttribuables($r->user(), array_diff($nouveaux, $actuels));
        $this->verifierRolesAttribuables($r->user(), array_diff($actuels, $nouveaux));
        $this->protegerDernierAdmin($utilisateur, $nouveaux);

        DB::transaction(function () use ($utilisateur, $d, $nouveaux, $actuels, $r, $soiMeme) {
            $utilisateur->fill(['name' => $d['name'], 'email' => $d['email'], 'service_id' => $d['service_id'] ?? null, 'direction_id' => $this->directionDuService($d['service_id'] ?? null)]);
            $champs = array_keys($utilisateur->getDirty());
            $utilisateur->save();
            if ($nouveaux != $actuels) {
                $this->synchroniserRoles($utilisateur, $nouveaux, $r->user());
                Audit::enregistrer('utilisateur.droits', $utilisateur, "Modification des rôles de {$utilisateur->identifiant}", [
                    'ajoutes' => Role::whereIn('id', array_diff($nouveaux, $actuels))->pluck('code')->all(),
                    'retires' => Role::whereIn('id', array_diff($actuels, $nouveaux))->pluck('code')->all()]);
            }
            if (! $soiMeme) { // ses propres droits ne se modifient pas (le formulaire est désactivé)
                $this->appliquerDroits($utilisateur, $this->droitsDemandes($d), $r->user());
            }
            Audit::enregistrer('utilisateur.modification', $utilisateur, "Modification du compte {$utilisateur->identifiant}", ['champs' => $champs]);
        });

        return back()->with('succes', 'Compte mis à jour.');
    }

    public function activation(Request $r, User $utilisateur): RedirectResponse
    {
        $actif = $r->validate(['actif' => ['required', 'boolean']])['actif'];
        if ($utilisateur->id === $r->user()->id) {
            return back()->with('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        if (! $actif) {
            $this->protegerDernierAdmin($utilisateur, []);
        }
        $utilisateur->actif = (bool) $actif;
        $utilisateur->save();
        if (! $actif) {
            DB::table('sessions')->where('user_id', $utilisateur->id)->delete(); // déconnexion immédiate
        }
        Audit::enregistrer($actif ? 'utilisateur.activation' : 'utilisateur.desactivation', $utilisateur, ($actif ? 'Activation' : 'Désactivation')." du compte {$utilisateur->identifiant}");

        return back()->with('succes', $actif ? 'Compte activé.' : 'Compte désactivé et sessions fermées.');
    }

    public function reinitialiserMotDePasse(Request $r, User $utilisateur): RedirectResponse
    {
        if ($utilisateur->id === $r->user()->id) {
            return back()->with('erreur', 'Utilisez « Mon mot de passe » pour votre propre compte.');
        }
        $mdp = $this->motDePasseTemporaire();
        $utilisateur->forceFill(['password' => Hash::make($mdp), 'doit_changer_mot_de_passe' => true, 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $utilisateur->id)->delete();
        Audit::enregistrer('utilisateur.reinitialisation_mot_de_passe', $utilisateur, "Mot de passe temporaire attribué à {$utilisateur->identifiant}");

        return back()->with('mot_de_passe_temporaire', $mdp)->with('succes', 'Mot de passe temporaire généré : il devra être changé à la première connexion.');
    }

    private function valider(Request $r, ?User $u = null): array
    {
        $profils = array_keys(Profils::PROFILS);
        if ($u?->exists && Profils::de($u->loadMissing('roles')) === null) {
            $profils[] = 'conserver';
        }

        return $r->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($u?->id)],
            'profil' => ['required', Rule::in($profils)],
            'service_id' => ['nullable', 'required_if:profil,chef_service', 'integer', 'exists:services,id'],
            'personnaliser' => ['nullable', 'boolean'],
            'droits' => ['nullable', 'array'],
            'droits.*' => ['string', Rule::in(collect(Profils::droitsProposes())->flatMap(fn ($g) => array_keys($g))->all())],
        ], ['profil.required' => 'Choisissez le profil du compte.', 'service_id.required_if' => 'Le service est obligatoire pour un responsable de structure.'],
            ['profil' => 'profil', 'service_id' => 'service']);
    }

    /** null : droits du profil, sans personnalisation ; sinon la liste des droits cochés. */
    private function droitsDemandes(array $d): ?array
    {
        return ! empty($d['personnaliser']) ? array_values(array_unique($d['droits'] ?? [])) : null;
    }

    /**
     * Personnalisation des droits (2.0.0) : les écarts avec le profil sont enregistrés (droit ajouté ou retiré).
     * Garde-fous : on ne donne pas un droit qu'on n'a pas soi-même ; les droits d'administration ne s'ajoutent ni ne se
     * retirent sans « roles.gerer » ; le dernier compte capable de gérer les comptes ne peut pas perdre ce droit.
     */
    private function appliquerDroits(User $u, ?array $droits, User $acteur): void
    {
        $u->viderCachePermissions();
        $avant = $u->droitsPersonnalises();
        $profil = array_keys($u->permissionsDuProfil());
        $nouveaux = [];
        if ($droits !== null) {
            $proposes = collect(Profils::droitsProposes())->flatMap(fn ($g) => array_keys($g))->all();
            foreach (array_diff($droits, $profil) as $c) {
                $nouveaux[$c] = true;
            }
            foreach (array_diff(array_intersect($profil, $proposes), $droits) as $c) {
                $nouveaux[$c] = false;
            }
        }
        ksort($avant);
        ksort($nouveaux);
        if ($avant === $nouveaux) {
            return;
        }
        $libelle = fn (array $codes) => collect($codes)->map(fn ($c) => '« '.(CataloguePermissions::PERMISSIONS[$c][1] ?? $c).' »')->implode(', ');
        $ajoutes = array_keys(array_filter($nouveaux));
        $nonDetenus = array_values(array_filter($ajoutes, fn ($c) => ! $acteur->aPermission($c)));
        if ($nonDetenus) {
            throw new RegleMetierException('Vous ne pouvez pas donner un droit que vous n\'avez pas vous-même : '.$libelle($nonDetenus).'.');
        }
        $admin = array_intersect(array_keys(array_diff_assoc($nouveaux, $avant) + array_diff_assoc($avant, $nouveaux)), self::PERMISSIONS_ADMIN);
        if ($admin && ! $acteur->aPermission('roles.gerer')) {
            throw new RegleMetierException('Seul un détenteur du droit « Attribuer le profil RH et consulter les droits » peut modifier les droits d\'administration : '.$libelle($admin).'.');
        }
        $apres = array_fill_keys($profil, true);
        foreach ($nouveaux as $c => $accorde) {
            if ($accorde) {
                $apres[$c] = true;
            } else {
                unset($apres[$c]);
            }
        }
        if ($u->aPermission('utilisateurs.gerer') && ! isset($apres['utilisateurs.gerer'])
            && User::where('actif', true)->whereKeyNot($u->id)->get()->filter(fn ($x) => $x->aPermission('utilisateurs.gerer'))->isEmpty()) {
            throw new RegleMetierException('Impossible : ce compte est le dernier à pouvoir gérer les comptes utilisateurs.');
        }

        $ids = Permission::whereIn('code', array_keys($nouveaux))->pluck('id', 'code');
        $u->permissionsPersonnalisees()->sync(collect($nouveaux)->mapWithKeys(fn ($accorde, $c) => [$ids[$c] => ['accorde' => $accorde, 'attribue_par' => $acteur->id]])->all());
        $u->viderCachePermissions();
        $diffAjout = array_keys(array_filter(array_diff_assoc($nouveaux, $avant)));
        Audit::enregistrer('utilisateur.droits_personnalises', $u, "Droits personnalisés de {$u->identifiant}", [
            'ajoutes' => array_keys(array_filter($nouveaux)), 'retires' => array_keys(array_filter($nouveaux, fn ($v) => ! $v)), 'modifies' => $diffAjout]);
    }

    private function directionDuService(?int $serviceId): ?int
    {
        return $serviceId ? Service::whereKey($serviceId)->value('direction_id') : null;
    }

    private function verifierRolesAttribuables(User $acteur, array $roleIds): void
    {
        if (! $roleIds || $acteur->aPermission('roles.gerer')) {
            return;
        }
        $sensibles = Role::whereIn('id', $roleIds)->whereHas('permissions', fn ($q) => $q->whereIn('code', self::PERMISSIONS_ADMIN))->pluck('nom');
        if ($sensibles->isNotEmpty()) {
            abort(403, 'Seul un détenteur de la permission « Gérer les rôles » peut attribuer ou retirer un rôle d\'administration ('.$sensibles->implode(', ').').');
        }
    }

    private function protegerDernierAdmin(User $u, array $nouveauxRoles): void
    {
        $admin = Role::where('code', 'admin_technique')->first();
        if (! $admin || ! $u->roles()->whereKey($admin->id)->exists() || in_array($admin->id, $nouveauxRoles, true)) {
            return;
        }
        $autres = $admin->users()->where('users.actif', true)->where('users.id', '!=', $u->id)->count();
        if ($autres === 0) {
            abort(422, 'Impossible : ce compte est le dernier administrateur technique actif.');
        }
    }

    private function synchroniserRoles(User $u, array $ids, User $par): void
    {
        $u->roles()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['attribue_le' => now(), 'attribue_par' => $par->id]])->all());
        $u->viderCachePermissions();
    }

    private function motDePasseTemporaire(): string
    {
        // 16 caractères dont majuscule, minuscule, chiffre et symbole (conforme à la politique)
        return Str::password(12, symbols: false).'A1a!'.Str::password(4, letters: false, symbols: false);
    }

    private function listes(): array
    {
        return ['services' => Service::actifs()->with('direction')->get()];
    }
}
