<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Module N — Paramètres institutionnels (identité, règles, notifications, exports, conservation). */
class ParametreController extends Controller
{
    public function index(): View
    {
        return view('admin.parametres.index', ['groupes' => Parametre::orderBy('id')->get()->groupBy('groupe')]);
    }

    public function update(Request $r): RedirectResponse
    {
        $params = Parametre::all()->keyBy('cle');
        $regles = [
            'logo' => ['nullable', 'file', 'max:1024', 'mimes:png,jpg,jpeg'],
            'photo' => ['nullable', 'file', 'max:6144', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=1200,min_height=700'],
        ];
        foreach ($params as $p) {
            $regles['p.'.str_replace('.', '__', $p->cle)] = match ($p->type) {
                'int' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'decimal' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'bool' => ['nullable', 'in:0,1'],
                'file' => ['prohibited'],
                default => ['nullable', 'string', 'max:2000'],
            };
        }
        $d = $r->validate($regles);
        $modifies = [];
        foreach ($params as $p) {
            if ($p->type === 'file') {
                continue;
            }
            $cle = 'p.'.str_replace('.', '__', $p->cle);
            $val = data_get($d, $cle);
            $val = $p->type === 'bool' ? ($val === '1' ? '1' : '0') : ($val === null ? null : (string) $val);
            if ($val !== $p->valeur) {
                $p->valeur = $val;
                $p->save();
                $modifies[] = $p->cle;
            }
        }
        foreach (['logo' => 'institution.logo', 'photo' => 'institution.photo_connexion'] as $champ => $cle) {
            if ($r->hasFile($champ) && isset($params[$cle])) {
                $nom = $champ.'-'.Str::random(12).'.'.$r->file($champ)->extension();
                Storage::disk('prive')->putFileAs('identite', $r->file($champ), $nom);
                $params[$cle]->update(['valeur' => $nom]);
                $modifies[] = $cle;
            }
        }
        Parametre::viderCache();
        Audit::enregistrer('parametres.modification', 'parametre', 'Modification des paramètres', ['cles' => $modifies]);

        return back()->with('succes', count($modifies).' paramètre(s) modifié(s).');
    }
}
