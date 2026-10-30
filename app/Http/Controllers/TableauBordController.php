<?php

namespace App\Http\Controllers;

use App\Models\ActionFormation;
use App\Models\Direction;
use App\Models\Domaine;
use App\Models\Service;
use App\Services\IndicateurService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableauBordController extends Controller
{
    /** Page d'accueil adaptée au profil connecté. */
    public function accueil(Request $r): RedirectResponse|View
    {
        $u = $r->user();
        if ($u->aPermission('tableau_bord.voir')) {
            return redirect()->route('tableau-bord');
        }
        if ($u->aUneDesPermissions('besoins.voir_service', 'besoins.voir_tous')) {
            return redirect()->route('besoins.index');
        }
        if ($u->aPermission('espace.personnel')) {
            return redirect()->route('espace.index');
        }

        return view('accueil-sans-droit');
    }

    public function index(Request $r, IndicateurService $ind): View
    {
        $f = $this->filtres($r);
        $f['annee'] ??= (int) now()->year;

        return view('tableau-bord.index', [
            'f' => $f,
            'k' => $ind->tableauDeBord($f),
            'mois' => $ind->parMois($f),
            'resume' => $ind->resume($f),
            'precedent' => $ind->resume(['annee' => $f['annee'] - 1] + $f),
            'comparatif' => $ind->comparatifAnnuel(),
            'annees' => ActionFormation::select('annee')->distinct()->orderByDesc('annee')->pluck('annee')->push((int) now()->year)->unique()->sortDesc()->values(),
            'directions' => Direction::actives()->get(),
            'services' => Service::actifs()->get(),
            'domaines' => Domaine::actifs()->get(),
        ]);
    }
}
