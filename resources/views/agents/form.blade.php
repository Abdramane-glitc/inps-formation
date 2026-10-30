<x-layout :titre="$agent->exists ? 'Modifier un agent' : 'Nouvel agent'">
    <x-entete :titre="$agent->exists ? 'Modifier la fiche '.$agent->matricule : 'Nouvel agent'" />
    <x-carte>
        <form method="POST" action="{{ $agent->exists ? route('agents.update', $agent) : route('agents.store') }}" class="space-y-4">
            @csrf @if ($agent->exists) @method('PUT') @endif
            <div class="grille-form">
                <x-champ nom="matricule" libelle="Matricule" :valeur="$agent->matricule" :requis="true" aide="Identifiant unique de l'agent." />
                <x-champ nom="fonction" libelle="Fonction / poste" :valeur="$agent->fonction" />
                <x-champ nom="nom" libelle="Nom" :valeur="$agent->nom" :requis="true" />
                <x-champ nom="prenom" libelle="Prénom(s)" :valeur="$agent->prenom" :requis="true" />
                <x-choix-service id="agent" class="md:col-span-2" nom-direction="direction_id" :valeur-service="$agent->service_id" :valeur-direction="$agent->direction_id" />
                <x-champ nom="statut_professionnel_id" libelle="Statut professionnel" type="select" :valeur="$agent->statut_professionnel_id" :options="$statuts->pluck('libelle', 'id')" />
                <x-champ nom="email_pro" libelle="E-mail professionnel (facultatif)" type="email" :valeur="$agent->email_pro" />
                <x-champ nom="telephone_pro" libelle="Téléphone professionnel (facultatif)" :valeur="$agent->telephone_pro" />
            </div>
            <p class="aide">Seules les informations strictement nécessaires à la gestion des formations sont collectées.</p>
            <div class="flex gap-2"><button class="btn btn-primaire">Enregistrer</button><a class="btn btn-secondaire" href="{{ $agent->exists ? route('agents.show', $agent) : route('agents.index') }}">Annuler</a></div>
        </form>
    </x-carte>
</x-layout>
