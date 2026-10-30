<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Formateur;
use App\Models\Organisme;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormateurController extends Controller
{
    public function index(Request $r): View
    {
        $f = $r->validate(['type' => ['nullable', 'in:interne,externe'], 'q' => ['nullable', 'string', 'max:100']]);

        return view('formateurs.index', ['formateurs' => Formateur::with(['organisme', 'agent'])->withCount('sessions')
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('nom', 'like', '%'.addcslashes($v, '%_').'%')->orWhere('domaines_expertise', 'like', '%'.addcslashes($v, '%_').'%')))
            ->orderBy('nom')->paginate(25)->withQueryString(), 'f' => $f]);
    }

    public function create(): View
    {
        return view('formateurs.form', ['fm' => new Formateur(['type' => 'externe', 'actif' => true])] + $this->listes());
    }

    public function store(Request $r): RedirectResponse
    {
        $fm = Formateur::create($this->donnees($r));
        Audit::enregistrer('formateur.creation', 'formateur', "Création du formateur {$fm->nom_complet}");

        return redirect()->route('formateurs.index')->with('succes', 'Formateur enregistré.');
    }

    public function edit(Formateur $formateur): View
    {
        return view('formateurs.form', ['fm' => $formateur] + $this->listes());
    }

    public function update(Request $r, Formateur $formateur): RedirectResponse
    {
        $formateur->update($this->donnees($r));

        return redirect()->route('formateurs.index')->with('succes', 'Formateur mis à jour.');
    }

    private function donnees(Request $r): array
    {
        $r->merge(['actif' => $r->boolean('actif')]);
        $d = $r->validate([
            'type' => ['required', 'in:interne,externe'],
            'agent_id' => ['nullable', 'required_if:type,interne', 'integer', 'exists:agents,id'],
            'organisme_id' => ['nullable', 'integer', 'exists:organismes,id'],
            'nom' => ['required_unless:type,interne', 'nullable', 'string', 'max:100'], 'prenom' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'], 'telephone' => ['nullable', 'string', 'max:40'],
            'domaines_expertise' => ['nullable', 'string', 'max:3000'], 'actif' => ['boolean'],
        ], ['agent_id.required_if' => 'Sélectionnez l\'agent concerné pour un formateur interne.']);
        if ($d['type'] === 'interne' && ! empty($d['agent_id'])) {
            $a = Agent::find($d['agent_id']);
            $d['nom'] = $a->nom;
            $d['prenom'] = $a->prenom;
            $d['organisme_id'] = null;
        } else {
            $d['agent_id'] = null;
        }

        return $d;
    }

    private function listes(): array
    {
        return ['agents' => Agent::where('actif', true)->orderBy('nom')->get(['id', 'matricule', 'nom', 'prenom']), 'organismes' => Organisme::where('actif', true)->orderBy('raison_sociale')->get()];
    }
}
