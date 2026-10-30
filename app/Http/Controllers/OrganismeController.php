<?php

namespace App\Http\Controllers;

use App\Models\AppreciationPrestataire;
use App\Models\ContactOrganisme;
use App\Models\Nomenclature;
use App\Models\Organisme;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Module F — Organismes (données contractuelles réservées à « organismes.contrats »). */
class OrganismeController extends Controller
{
    private function regles(): array
    {
        return [
            'raison_sociale' => ['required', 'string', 'max:190'], 'sigle' => ['nullable', 'string', 'max:50'], 'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'], 'site_web' => ['nullable', 'url', 'max:190'],
            'domaines_intervention' => ['nullable', 'string', 'max:3000'], 'actif' => ['boolean'],
            'identifiant_fiscal' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function index(Request $r): View
    {
        $q = $r->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;

        return view('organismes.index', ['organismes' => Organisme::withCount(['actions', 'formateurs'])->withAvg('appreciations', 'note')
            ->when($q, fn ($w) => $w->where('raison_sociale', 'like', '%'.addcslashes($q, '%_').'%')->orWhere('sigle', 'like', '%'.addcslashes($q, '%_').'%'))
            ->orderBy('raison_sociale')->paginate(25)->withQueryString(), 'q' => $q]);
    }

    public function create(): View
    {
        return view('organismes.form', ['o' => new Organisme(['actif' => true])]);
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $this->donnees($r);
        $o = Organisme::create($d);
        Audit::enregistrer('organisme.creation', $o, "Création de l'organisme {$o->raison_sociale}");

        return redirect()->route('organismes.show', $o)->with('succes', 'Organisme enregistré.');
    }

    public function show(Request $r, Organisme $organisme): View
    {
        $contrats = $r->user()->aPermission('organismes.contrats');
        $organisme->load(['contacts', 'formateurs', 'appreciations.user', 'appreciations.action', 'actions' => fn ($q) => $q->orderByDesc('annee')]);
        if ($contrats) {
            $organisme->load(['conventions', 'documents.categorie']);
        }

        return view('organismes.show', ['o' => $organisme, 'contrats' => $contrats, 'categoriesDoc' => Nomenclature::deType('categorie_document')->get()]);
    }

    public function edit(Organisme $organisme): View
    {
        return view('organismes.form', ['o' => $organisme]);
    }

    public function update(Request $r, Organisme $organisme): RedirectResponse
    {
        $organisme->fill($this->donnees($r));
        $champs = array_keys($organisme->getDirty());
        $organisme->save();
        Audit::enregistrer('organisme.modification', $organisme, "Modification de l'organisme {$organisme->raison_sociale}", ['champs' => $champs]);

        return redirect()->route('organismes.show', $organisme)->with('succes', 'Organisme mis à jour.');
    }

    public function ajouterContact(Request $r, Organisme $organisme): RedirectResponse
    {
        $d = $r->validate(['nom' => ['required', 'string', 'max:190'], 'fonction' => ['nullable', 'string', 'max:190'], 'telephone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190']]);
        $organisme->contacts()->create($d);

        return back()->with('succes', 'Contact ajouté.');
    }

    public function retirerContact(ContactOrganisme $contact): RedirectResponse
    {
        $contact->delete();
        Audit::enregistrer('organisme.contact_retrait', $contact->organisme, 'Retrait d\'une personne de contact');

        return back()->with('succes', 'Contact retiré.');
    }

    public function ajouterConvention(Request $r, Organisme $organisme): RedirectResponse
    {
        $d = $r->validate(['reference' => ['required', 'string', 'max:100'], 'objet' => ['required', 'string', 'max:255'], 'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut']]);
        if ($organisme->conventions()->where('reference', $d['reference'])->exists()) {
            return back()->withInput()->with('erreur', 'Cette référence de convention existe déjà pour cet organisme.');
        }
        $c = $organisme->conventions()->create($d);
        Audit::enregistrer('organisme.convention', $organisme, "Ajout de la convention {$c->reference}");

        return back()->with('succes', 'Convention enregistrée.');
    }

    public function apprecier(Request $r, Organisme $organisme): RedirectResponse
    {
        $d = $r->validate(['note' => ['required', 'integer', 'between:1,5'], 'action_id' => ['nullable', 'integer', 'exists:actions_formation,id'], 'commentaire' => ['nullable', 'string', 'max:2000']]);
        AppreciationPrestataire::create($d + ['organisme_id' => $organisme->id, 'user_id' => $r->user()->id]);

        return back()->with('succes', 'Appréciation enregistrée.');
    }

    private function donnees(Request $r): array
    {
        $r->merge(['actif' => $r->boolean('actif')]);
        $d = $r->validate($this->regles());
        if (! $r->user()->aPermission('organismes.contrats')) {
            unset($d['identifiant_fiscal']); // donnée contractuelle : modifiable uniquement par les habilités
        }

        return $d;
    }
}
