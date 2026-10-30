<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Profils;
use Illuminate\View\View;

/**
 * « Profils et droits » (version 1.6.0) : deux profils, présentés en lecture seule (qui peut faire quoi).
 * Depuis la 2.0.0, un compte peut avoir des droits personnalisés (fiche du compte) : ils sont listés ici.
 * Les droits affichés sont lus en base, à partir des rôles réellement attribués à chaque profil.
 * La création et la modification de rôles depuis l'application sont retirées.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $droits = [];
        foreach (Profils::PROFILS as $code => [, $roles]) {
            $droits[$code] = Role::whereIn('code', $roles)->with('permissions:id,code')->get()->flatMap->permissions->pluck('code')->unique()->all();
        }
        $permissions = Permission::where('code', '!=', 'espace.personnel')->orderBy('module')->orderBy('id')->get()->groupBy('module');
        $comptes = collect(Profils::PROFILS)->map(fn ($p) => Role::where('code', $p[1][0])->withCount(['users' => fn ($q) => $q->where('actif', true)])->value('users_count') ?? 0);

        // 2.0.0 : comptes dont les droits diffèrent de leur profil
        $personnalises = User::whereHas('permissionsPersonnalisees')->with(['roles', 'permissionsPersonnalisees'])->orderBy('name')->get();

        return view('admin.roles.index', ['droits' => $droits, 'permissions' => $permissions, 'comptes' => $comptes, 'personnalises' => $personnalises,
            'autresRoles' => Role::whereNotIn('code', collect(Profils::PROFILS)->flatMap(fn ($p) => $p[1]))->withCount('users')->orderBy('nom')->get()]);
    }
}
