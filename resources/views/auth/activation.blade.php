<x-invite titre="Activer mon compte">
    <h1 class="invite-titre">Première connexion</h1>
    <p class="invite-sous-titre">Saisissez votre matricule et le code à 6 chiffres donné par la Direction des ressources humaines, puis choisissez votre mot de passe.</p>

    <div class="mt-7"><x-messages :resume-erreurs="false" /></div>

    <form method="POST" action="{{ route('activation.store') }}" class="space-y-5" novalidate>
        @csrf
        <x-champ nom="matricule" libelle="Matricule" :requis="true" icone="espace" :attrs="['autocomplete' => 'username', 'autofocus' => 'autofocus', 'spellcheck' => 'false', 'autocapitalize' => 'characters']" />
        <x-champ nom="code" libelle="Code d'activation" :requis="true" icone="cle" aide="6 chiffres, par exemple 482 913." :attrs="['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 12]" />
        <x-champ nom="password" libelle="Choisissez votre mot de passe" type="password" :requis="true" :basculer="true" :aide="'Au moins '.config('inps.securite.mot_de_passe_longueur', 12).' caractères, avec une majuscule, une minuscule, un chiffre et un symbole (exemple : Formation-2026!).'" :attrs="['autocomplete' => 'new-password']" />
        <x-champ nom="password_confirmation" libelle="Retapez votre mot de passe" type="password" :requis="true" :attrs="['autocomplete' => 'new-password']" />
        <button type="submit" class="btn btn-primaire btn-connexion">Activer mon compte</button>
    </form>
    <p class="mt-5 text-sm"><a href="{{ route('login') }}">Retour à la connexion</a></p>
</x-invite>
