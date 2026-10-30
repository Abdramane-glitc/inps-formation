@php use App\Models\FormationDiplomante as D; @endphp
<x-layout titre="Formation diplômante">
    <x-entete :titre="$d->exists ? 'Modifier la formation diplômante '.$d->reference : 'Nouvelle formation diplômante'" sous-titre="Aucun montant n'est enregistré : seul le type de prise en charge est indiqué." />
    <x-carte>
        <form method="POST" action="{{ $d->exists ? route('diplomantes.update', $d) : route('diplomantes.store') }}" class="space-y-6">
            @csrf @if ($d->exists) @method('PUT') @endif
            <fieldset class="grille-form">
                <legend class="font-semibold mb-2">Agent et diplôme</legend>
                <x-champ class="md:col-span-2" nom="agent_id" libelle="Agent" type="select" :valeur="$d->agent_id" :options="$agents->mapWithKeys(fn ($a) => [$a->id => $a->matricule.' — '.$a->nom_complet])" :requis="true" />
                <x-champ nom="niveau" libelle="Niveau du diplôme" type="select" :valeur="$d->niveau" :options="D::NIVEAUX" :requis="true" />
                <x-champ nom="intitule" libelle="Intitulé ou spécialité" :valeur="$d->intitule" :requis="true" aide="Par exemple : Master en gestion des ressources humaines." />
            </fieldset>
            <fieldset class="grille-form">
                <legend class="font-semibold mb-2">Établissement</legend>
                <x-champ class="md:col-span-2" nom="etablissement" libelle="Établissement" :valeur="$d->etablissement" :requis="true" />
                <x-champ nom="ville" libelle="Ville" :valeur="$d->ville" />
                <x-champ nom="pays" libelle="Pays" :valeur="$d->pays ?? ($d->exists ? null : 'Mali')" />
            </fieldset>
            <fieldset class="grille-form">
                <legend class="font-semibold mb-2">Déroulement</legend>
                <x-champ nom="annee_debut" libelle="Année académique (année de début)" type="number" :valeur="$d->annee_debut" :requis="true" :attrs="['min' => 1980, 'max' => 2100]" aide="2026 pour l'année 2026-2027." />
                <x-champ nom="duree_annees" libelle="Durée prévue (années)" type="number" :valeur="$d->duree_annees" :attrs="['min' => 1, 'max' => 10]" />
                <x-champ nom="mode" libelle="Mode" type="select" :valeur="$d->mode" :options="D::MODES" :vide="false" :requis="true" />
                <x-champ nom="prise_en_charge" libelle="Prise en charge" type="select" :valeur="$d->prise_en_charge" :options="D::PRISES_EN_CHARGE" :requis="true" />
                <x-champ nom="situation" libelle="Situation" type="select" :valeur="$d->situation" :options="D::SITUATIONS" :vide="false" :requis="true" />
                <x-champ nom="date_obtention" libelle="Date d'obtention du diplôme" type="date" :valeur="$d->date_obtention" aide="Seulement si la situation est « Diplôme obtenu »." />
                <x-champ class="md:col-span-2" nom="observations" libelle="Observations" type="textarea" :valeur="$d->observations" />
            </fieldset>
            <div class="flex gap-2"><button class="btn btn-primaire">Enregistrer</button><a class="btn btn-secondaire" href="{{ $d->exists ? route('diplomantes.show', $d) : route('diplomantes.index') }}">Annuler</a></div>
        </form>
    </x-carte>
</x-layout>
