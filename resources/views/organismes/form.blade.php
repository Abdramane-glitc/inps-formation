<x-layout :titre="$o->exists ? 'Modifier l\'organisme' : 'Nouvel organisme'">
    <x-entete :titre="$o->exists ? $o->raison_sociale : 'Nouvel organisme de formation'" />
    <x-carte>
        <form method="POST" action="{{ $o->exists ? route('organismes.update', $o) : route('organismes.store') }}" class="space-y-4">
            @csrf @if ($o->exists) @method('PUT') @endif
            <div class="grille-form">
                <x-champ nom="raison_sociale" libelle="Raison sociale" :valeur="$o->raison_sociale" :requis="true" />
                <x-champ nom="sigle" libelle="Sigle" :valeur="$o->sigle" />
                <x-champ nom="adresse" libelle="Adresse" :valeur="$o->adresse" />
                <x-champ nom="telephone" libelle="Téléphone" :valeur="$o->telephone" />
                <x-champ nom="email" libelle="E-mail" type="email" :valeur="$o->email" />
                <x-champ nom="site_web" libelle="Site web" type="url" :valeur="$o->site_web" />
                <x-champ class="md:col-span-2" nom="domaines_intervention" libelle="Domaines d'intervention" type="textarea" :valeur="$o->domaines_intervention" />
                @permission('organismes.contrats')<x-champ nom="identifiant_fiscal" libelle="Identifiant fiscal / NIF (donnée contractuelle)" :valeur="$o->identifiant_fiscal" />@endpermission
                <x-champ nom="actif" libelle="Organisme actif" type="checkbox" :valeur="$o->actif" />
            </div>
            <div class="flex gap-2"><button class="btn btn-primaire">Enregistrer</button><a class="btn btn-secondaire" href="{{ $o->exists ? route('organismes.show', $o) : route('organismes.index') }}">Annuler</a></div>
        </form>
    </x-carte>
</x-layout>
