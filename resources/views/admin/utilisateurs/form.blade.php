@php
    $soi = $u->exists && $u->id === auth()->id();
    $actuel = $u->exists ? \App\Support\Profils::de($u->loadMissing('roles')) : null;
    $autre = $u->exists && $actuel === null && $u->roles->isNotEmpty();
    $choisi = old('profil', $actuel ?? ($autre ? 'conserver' : null));
    // Droits personnalisables (2.0.0)
    $P = \App\Support\Profils::class;
    $profilsDuDroit = [];
    foreach (array_keys($P::PROFILS) as $pc) { foreach ($P::droitsDuProfil($pc) as $dc) { $profilsDuDroit[$dc][] = $pc; } }
    $perso = $u->exists && $u->droitsPersonnalises();
    $persoCoche = (bool) old('personnaliser', $perso);
    $droitsCoches = collect(old('droits', $u->exists ? array_keys($u->permissionCodes()) : $P::droitsDuProfil($choisi ?? 'chef_service')));
@endphp
<x-layout titre="Compte utilisateur">
    <x-entete :titre="$u->exists ? $u->name : 'Nouveau compte'" :sous-titre="$u->exists ? $u->identifiant : 'Un mot de passe temporaire sera généré ; il devra être changé à la première connexion.'">
        @if ($u->exists && app(\App\Services\SuppressionService::class)->obstacleCompte($u, auth()->user()) === null)
            <x-action-form :url="route('suppression.unique', ['comptes', $u->id])" methode="DELETE" libelle="Supprimer le compte" classe="btn btn-danger" confirmer="Supprimer définitivement ce compte jamais utilisé ?" />
        @endif
        @if ($u->exists && ! $soi)
            <x-action-form :url="route('admin.utilisateurs.reinitialiser', $u)" libelle="Générer un mot de passe temporaire" classe="btn btn-secondaire" confirmer="Générer un mot de passe temporaire ? Les sessions de l'utilisateur seront fermées." />
            @if ($u->actif)
                <x-action-form :url="route('admin.utilisateurs.activation', $u)" libelle="Désactiver le compte" classe="btn btn-danger" :champs="['actif' => 0]" confirmer="Désactiver ce compte ? L'utilisateur sera déconnecté immédiatement." />
            @else
                <x-action-form :url="route('admin.utilisateurs.activation', $u)" libelle="Réactiver" classe="btn btn-secondaire" :champs="['actif' => 1]" />
            @endif
        @endif
    </x-entete>
    @if ($u->exists && \App\Support\Profils::estAgent($u))
        <x-carte titre="Compte d'agent">
            <p class="text-sm">Ce compte permet à l'agent de consulter ses formations, ses convocations et ses attestations. Il se connecte avec son matricule
                @if ($u->agent)(<strong>{{ $u->agent->matricule }}</strong>)@endif. Un nouveau mot de passe se donne depuis
                <a href="{{ route('agents.acces') }}">Personnel › Accès des agents</a>@if ($u->agent) ou <a href="{{ route('agents.show', $u->agent) }}">la fiche de l'agent</a>@endif.</p>
            <p class="text-sm mt-2">Dernière connexion : {{ $u->derniere_connexion_le ? date_fr($u->derniere_connexion_le, true) : 'jamais' }}.</p>
        </x-carte>
    @else
    <form method="POST" action="{{ $u->exists ? route('admin.utilisateurs.update', $u) : route('admin.utilisateurs.store') }}" class="space-y-5">
        @csrf @if ($u->exists) @method('PUT') @endif
        <x-carte titre="Identité">
            <div class="grille-form">
                <x-champ nom="name" libelle="Nom complet" :valeur="$u->name" :requis="true" />
                <x-champ nom="email" libelle="E-mail (identifiant de connexion)" type="email" :valeur="$u->email" :requis="true" />
            </div>
        </x-carte>
        <x-carte titre="Profil">
            @if ($soi)<p class="mb-3 text-sm text-alerte">Vous ne pouvez pas modifier votre propre profil.</p>@endif
            <fieldset class="grid gap-3 md:grid-cols-2" @disabled($soi)>
                <legend class="sr-only">Profil du compte</legend>
                @foreach (\App\Support\Profils::PROFILS as $code => [$libelle, , $description])
                    <label class="font-normal flex gap-3 items-start p-3 rounded border border-filet has-[:checked]:border-primaire has-[:checked]:bg-primaire-clair">
                        <input type="radio" name="profil" value="{{ $code }}" @checked($choisi === $code) class="mt-1">
                        <span><span class="font-semibold">{{ $libelle }}</span><span class="block text-sm text-encre-2">{{ $description }}</span></span>
                    </label>
                @endforeach
                @if ($autre)
                    <label class="font-normal flex gap-3 items-start p-3 rounded border border-filet md:col-span-2">
                        <input type="radio" name="profil" value="conserver" @checked($choisi === 'conserver') class="mt-1">
                        <span><span class="font-semibold">Garder les droits actuels</span><span class="block text-sm text-encre-2">Compte créé avant la simplification : {{ $u->roles->pluck('nom')->implode(', ') }}.</span></span>
                    </label>
                @endif
            </fieldset>
            @if ($soi)<input type="hidden" name="profil" value="{{ $choisi }}">@endif
            @error('profil')<p class="erreur-champ"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@enderror
            <div class="mt-4 max-w-3xl">
                <x-choix-service id="compte" :valeur-service="$u->service_id" :valeur-direction="$u->direction_id" aide="Choisissez d'abord la direction : seuls ses services sont proposés. Obligatoire pour un responsable de structure : il ne verra que les demandes, les agents et les formations de ce service." />
            </div>
            <details class="mt-5 rounded border border-filet" id="droits" @if ($persoCoche || $errors->hasAny(['droits', 'droits.*'])) open @endif>
                <summary class="cursor-pointer px-3 py-2 font-semibold text-sm">Personnaliser les droits de ce compte (facultatif)@if ($perso) — <span class="text-alerte">droits personnalisés</span>@endif</summary>
                <fieldset class="space-y-3 px-3 pb-3" @disabled($soi)>
                    <legend class="sr-only">Droits du compte</legend>
                    <label class="font-normal flex gap-2 items-start"><input type="checkbox" name="personnaliser" value="1" @checked($persoCoche) data-active-droits="#liste-droits" class="mt-1">
                        <span>Donner à ce compte des droits différents de ceux de son profil<span class="block text-sm text-encre-2">Sinon, le compte a exactement les droits de son profil.</span></span></label>
                    <div id="liste-droits" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($P::droitsProposes() as $module => $droits)
                            <div><p class="font-semibold text-encre-2 text-sm mb-1">{{ $module }}</p>
                                @foreach ($droits as $code => $lib)
                                    <label class="font-normal flex gap-2 items-start text-sm"><input type="checkbox" name="droits[]" value="{{ $code }}" data-profils="{{ implode(' ', $profilsDuDroit[$code] ?? []) }}" @checked($droitsCoches->contains($code)) class="mt-1"> <span>{{ $lib }}@if (! empty($profilsDuDroit[$code]))<span class="block text-xs text-encre-3">Profil {{ collect($profilsDuDroit[$code])->map(fn ($x) => $P::libelle($x))->implode(', ') }}</span>@endif</span></label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    @error('droits.*')<p class="erreur-champ"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@enderror
                    <p class="aide">Choisir un autre profil remet ses droits par défaut. Vous ne pouvez pas donner un droit que vous n'avez pas vous-même.</p>
                </fieldset>
            </details>
            <p class="aide mt-3">Toute modification est enregistrée dans le journal d'audit.</p>
        </x-carte>
        <div class="flex gap-2"><button class="btn btn-primaire">Enregistrer</button><a class="btn btn-secondaire" href="{{ route('admin.utilisateurs.index') }}">Retour</a></div>
    </form>
    @endif
</x-layout>
