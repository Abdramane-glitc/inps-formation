<x-invite titre="Connexion">
    <h1 class="invite-titre">Connectez-vous<br>à votre espace formation</h1>
    <p class="invite-sous-titre">Saisissez votre adresse e-mail professionnelle ou, pour les agents, votre matricule, puis votre mot de passe.</p>

    <div class="mt-7"><x-messages :resume-erreurs="false" /></div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
        @csrf
        <x-champ nom="email" libelle="Adresse e-mail ou matricule" :requis="true" icone="espace"
                 :attrs="['autocomplete' => 'username', 'autofocus' => 'autofocus', 'spellcheck' => 'false', 'autocapitalize' => 'none']" />
        <div>
            <x-champ nom="password" libelle="Mot de passe" type="password" :requis="true" :basculer="true" icone="cadenas" :attrs="['autocomplete' => 'current-password']" />
            <p class="mt-2 text-right text-sm"><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>
        </div>
        <button type="submit" class="btn btn-primaire btn-connexion">Se connecter <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></button>
    </form>
    <div class="invite-encart">
        <div>
            <p class="font-semibold">Agent : première connexion ?</p>
            <p class="mt-0.5">Avec le code à 6 chiffres donné par la RH, choisissez votre mot de passe.</p>
        </div>
        <a class="btn btn-secondaire" href="{{ route('activation') }}">Activer mon compte</a>
    </div>
</x-invite>
