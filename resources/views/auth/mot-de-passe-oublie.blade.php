<x-invite titre="Mot de passe oublié">
    <h1 class="invite-titre">Mot de passe oublié</h1>
    <div class="mt-6"><x-messages :resume-erreurs="false" /></div>
    @if ($smtp)
        <p class="text-[0.95rem] text-encre-2 mb-6">Indiquez votre adresse professionnelle. Si un compte actif y correspond, vous recevrez un lien valable {{ config('auth.passwords.users.expire') }} minutes pour choisir un nouveau mot de passe.</p>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <x-champ nom="email" libelle="Adresse e-mail professionnelle" type="email" :requis="true" :attrs="['autocomplete' => 'username']" />
            <button class="btn btn-primaire btn-connexion">Recevoir le lien</button>
        </form>
    @else
        <div class="message message-info"><x-icone nom="info" class="h-5 w-5 mt-px" />
            <p>L'envoi d'e-mails n'est pas configuré sur ce serveur. Demandez à l'administrateur de l'application de vous attribuer un mot de passe temporaire ; vous le changerez dès votre connexion.</p></div>
    @endif
    <p class="mt-6 text-sm"><a href="{{ route('login') }}">Retour à la connexion</a></p>
</x-invite>
