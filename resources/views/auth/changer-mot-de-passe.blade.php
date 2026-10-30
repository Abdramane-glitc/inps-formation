<x-layout titre="Mon mot de passe">
    <x-entete titre="Changer mon mot de passe" />
    <x-carte class="max-w-lg">
        <form method="POST" action="{{ route('mot-de-passe.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <x-champ nom="mot_de_passe_actuel" libelle="Mot de passe actuel" type="password" :requis="true" :basculer="true" :attrs="['autocomplete' => 'current-password']" />
            <x-champ nom="password" libelle="Nouveau mot de passe" type="password" :requis="true" :basculer="true" :aide="'Au moins '.config('inps.securite.mot_de_passe_longueur').' caractères, avec majuscule, minuscule, chiffre et caractère spécial.'" :attrs="['autocomplete' => 'new-password']" />
            <x-champ nom="password_confirmation" libelle="Confirmation" type="password" :requis="true" :attrs="['autocomplete' => 'new-password']" />
            <p class="text-xs text-slate-500">Vos autres sessions ouvertes seront fermées.</p>
            <button class="btn btn-primaire">Enregistrer</button>
        </form>
    </x-carte>
</x-layout>
