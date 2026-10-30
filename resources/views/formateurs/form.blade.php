<x-layout titre="Formateur">
    <x-entete :titre="$fm->exists ? 'Modifier le formateur' : 'Nouveau formateur'" />
    <x-carte>
        <form method="POST" action="{{ $fm->exists ? route('formateurs.update', $fm) : route('formateurs.store') }}" class="space-y-4">
            @csrf @if ($fm->exists) @method('PUT') @endif
            <div class="grille-form">
                <x-champ nom="type" libelle="Type" type="select" :valeur="$fm->type" :options="['interne' => 'Interne (agent de l\'INPS)', 'externe' => 'Externe']" :vide="false" :requis="true" />
                <x-champ nom="agent_id" libelle="Agent (formateur interne)" type="select" :valeur="$fm->agent_id" :options="$agents->mapWithKeys(fn ($a) => [$a->id => $a->matricule.' — '.$a->nom_complet])" />
                <x-champ nom="nom" libelle="Nom (formateur externe)" :valeur="$fm->type === 'externe' ? $fm->nom : null" />
                <x-champ nom="prenom" libelle="Prénom (formateur externe)" :valeur="$fm->type === 'externe' ? $fm->prenom : null" />
                <x-champ nom="organisme_id" libelle="Organisme de rattachement (externe)" type="select" :valeur="$fm->organisme_id" :options="$organismes->pluck('raison_sociale', 'id')" />
                <x-champ nom="email" libelle="E-mail" type="email" :valeur="$fm->email" />
                <x-champ nom="telephone" libelle="Téléphone" :valeur="$fm->telephone" />
                <x-champ class="md:col-span-2" nom="domaines_expertise" libelle="Domaines d'expertise" type="textarea" :valeur="$fm->domaines_expertise" />
                <x-champ nom="actif" libelle="Formateur actif" type="checkbox" :valeur="$fm->actif" />
            </div>
            <div class="flex gap-2"><button class="btn btn-primaire">Enregistrer</button><a class="btn btn-secondaire" href="{{ route('formateurs.index') }}">Annuler</a></div>
        </form>
    </x-carte>
</x-layout>
