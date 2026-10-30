<x-layout titre="Accès des agents">
    <x-entete titre="Accès des agents à l'application" sous-titre="Chaque agent active son compte avec un code à 6 chiffres, puis se connecte avec son matricule et son mot de passe.">
        <a class="btn btn-secondaire" href="{{ route('agents.acces.liste', request()->query()) }}">Imprimer la liste des codes</a>
        <a class="btn btn-secondaire" href="{{ route('agents.index') }}">Retour au personnel</a>
    </x-entete>
    <div class="message message-info mb-5"><x-icone nom="info" class="h-5 w-5 flex-none" /><div>
        <p><strong>1.</strong> Cochez les agents, puis « Donner un code ». <strong>2.</strong> Transmettez à chacun son code (de vive voix, par message ou avec la liste imprimée).
            <strong>3.</strong> L'agent ouvre l'application, clique sur « Activer mon compte », saisit son matricule et son code, et choisit son mot de passe.</p>
        <p class="mt-1">Un code sert une fois et reste valable {{ $jours }} jours. Cliquer de nouveau ne le change pas. {{ $sansAcces }} agent(s) actif(s) n'ont pas encore d'accès.</p>
    </div></div>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-4 items-end no-print">
        <div><label for="q">Matricule ou nom</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div>
        <div><label for="sv">Service</label><select id="sv" name="service_id"><option value="">Tous</option>
            @foreach ($services as $dir => $liste)<optgroup label="{{ $dir }}">@foreach ($liste as $id => $nom)<option value="{{ $id }}" @selected(($f['service_id'] ?? null) == $id)>{{ $nom }}</option>@endforeach</optgroup>@endforeach</select></div>
        <div><label for="et">Situation</label><select id="et" name="etat"><option value="">Tous</option>
            <option value="sans" @selected(($f['etat'] ?? null) === 'sans')>Pas encore d'accès</option>
            <option value="code" @selected(($f['etat'] ?? null) === 'code')>Code en attente d'activation</option>
            <option value="actives" @selected(($f['etat'] ?? null) === 'actives')>Compte activé</option></select></div>
        <div><button class="btn btn-primaire">Filtrer</button></div>
    </form>
    <x-carte sans-marge>
        @if ($agents->isEmpty())
            <x-vide message="Aucun agent actif pour ces critères." />
        @else
            <form method="POST" action="{{ route('agents.acces.generer') }}" id="form-acces">@csrf</form>
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-filet">
                <label class="font-normal inline-flex items-center gap-2 text-sm"><input type="checkbox" data-tout-cocher=".sel-acces"> Tout cocher (cette page)</label>
                <button form="form-acces" class="btn btn-primaire">Donner un code aux agents cochés</button>
            </div>
            <div class="overflow-x-auto"><table>
                <thead><tr><th class="w-10"></th><th>Agent</th><th>Service</th><th>Code</th><th>Situation</th><th></th></tr></thead>
                <tbody>@foreach ($agents as $ag)
                    @php $u = $ag->user; @endphp
                    <tr>
                        <td><input form="form-acces" type="checkbox" class="sel-acces" name="agents[]" value="{{ $ag->id }}" aria-label="Sélectionner {{ $ag->nom_complet }}"></td>
                        <td><a href="{{ route('agents.show', $ag) }}">{{ $ag->nom_complet }}</a><div class="text-xs text-encre-3">{{ $ag->matricule }}</div></td>
                        <td>{{ $ag->service?->nom ?? '—' }}</td>
                        <td>@if ($u?->aCodeValide())<span class="font-mono text-lg font-semibold tracking-wider">{{ $u->code_activation_affiche }}</span><div class="text-xs text-encre-3">jusqu'au {{ date_fr($u->code_activation_expire_le) }}</div>@elseif ($u?->code_activation)<span class="text-sm text-erreur">expiré ou bloqué</span>@else — @endif</td>
                        <td>@if (! $u)<x-badge texte="Pas d'accès" couleur="gris" />@elseif (! $u->actif)<x-badge texte="Désactivé" couleur="rouge" />@elseif ($u->aCodeValide())<x-badge texte="À activer" couleur="orange" />@elseif ($u->derniere_connexion_le)<x-badge texte="Activé" couleur="vert" /> <span class="text-xs text-encre-3">dernière connexion {{ date_fr($u->derniere_connexion_le) }}</span>@else<x-badge texte="Sans code valable" couleur="gris" />@endif</td>
                        <td class="whitespace-nowrap">@if ($u)<x-action-form :url="route('agents.acces.nouveau-code', $ag)" libelle="Nouveau code" confirmer="Donner un nouveau code à {{ $ag->nom_complet }} ? L'ancien code ne fonctionnera plus (son mot de passe actuel reste valable jusqu'à l'utilisation du nouveau code)." />@endif</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <div class="px-4 py-3">{{ $agents->links() }}</div>
        @endif
    </x-carte>
</x-layout>
