<x-layout titre="Utilisateurs">
    <x-entete titre="Comptes utilisateurs"><a class="btn btn-primaire" href="{{ route('admin.utilisateurs.create') }}">Nouveau compte</a></x-entete>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-5 items-end no-print">
        <div class="md:col-span-2"><label for="q">Nom ou e-mail</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div>
        <div><label for="pr">Profil</label><select id="pr" name="profil"><option value="">Tous</option>@foreach (\App\Support\Profils::PROFILS as $code => [$libelle])<option value="{{ $code }}" @selected(($f['profil'] ?? '') === $code)>{{ $libelle }}</option>@endforeach<option value="agent" @selected(($f['profil'] ?? '') === 'agent')>Agents (accès personnel)</option></select></div>
        <div><label for="ac">État</label><select id="ac" name="actif"><option value="">Tous</option><option value="1" @selected(($f['actif'] ?? '') === '1')>Actifs</option><option value="0" @selected(($f['actif'] ?? '') === '0')>Désactivés</option></select></div>
        <div><button class="btn btn-primaire">Filtrer</button></div>
    </form>
    <x-carte sans-marge>
        <div class="overflow-x-auto"><table>
            <thead><tr><th>Nom</th><th>E-mail</th><th>Profil</th><th>Service</th><th>Dernière connexion</th><th>État</th></tr></thead>
            <tbody>@forelse ($users as $u)
                <tr><td><a href="{{ route('admin.utilisateurs.edit', $u) }}">{{ $u->name }}</a></td><td>{{ $u->identifiant }}</td><td>{{ \App\Support\Profils::affichage($u) }}</td><td>{{ $u->service?->nom }}</td><td>{{ date_fr($u->derniere_connexion_le, true) }}</td>
                    <td>@if ($u->actif)<x-badge texte="Actif" couleur="vert" />@else<x-badge texte="Désactivé" couleur="rouge" />@endif @if ($u->doit_changer_mot_de_passe)<x-badge texte="Mot de passe à changer" couleur="orange" />@endif</td></tr>
            @empty <tr><td colspan="6"><x-vide message="Aucun compte." /></td></tr> @endforelse</tbody>
        </table></div>
        {{ $users->links() }}
    </x-carte>
</x-layout>
