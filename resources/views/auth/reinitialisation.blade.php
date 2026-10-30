<x-invite titre="Nouveau mot de passe">
    <h1 class="invite-titre">Choisir un nouveau mot de passe</h1>
    <div class="mt-6"><x-messages :resume-erreurs="false" /></div>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-champ nom="email" libelle="Adresse e-mail professionnelle" type="email" :valeur="$email" :requis="true" :attrs="['autocomplete' => 'username']" />
        <x-champ nom="password" libelle="Nouveau mot de passe" type="password" :requis="true" :basculer="true" :aide="'Au moins '.config('inps.securite.mot_de_passe_longueur').' caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial.'" :attrs="['autocomplete' => 'new-password']" />
        <x-champ nom="password_confirmation" libelle="Confirmer le mot de passe" type="password" :requis="true" :basculer="true" :attrs="['autocomplete' => 'new-password']" />
        <button class="btn btn-primaire btn-connexion">Enregistrer le mot de passe</button>
    </form>
</x-invite>
