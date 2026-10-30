<x-layout titre="Demandes de formation">
    <x-entete titre="Demandes de formation" :sous-titre="auth()->user()->aPermission('besoins.voir_tous') ? 'Tous les services' : 'Demandes de votre service'">
        @can('create', \App\Models\BesoinFormation::class)<a class="btn btn-primaire" href="{{ route('besoins.create') }}">Nouvelle demande</a>@endcan
    </x-entete>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-6 items-end no-print">
        <div class="md:col-span-2"><label for="q">Recherche</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Intitulé ou référence"></div>
        <div><label for="an">Année</label><select id="an" name="annee"><option value="">Toutes</option>@foreach ($annees as $a)<option @selected(($f['annee'] ?? '') == $a)>{{ $a }}</option>@endforeach</select></div>
        <div><label for="st">Statut</label><select id="st" name="statut"><option value="">Tous</option>@foreach (\App\Models\BesoinFormation::optionsFiltre() as $k => $l)<option value="{{ $k }}" @selected(($f['statut'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        @if ($services->isNotEmpty())
            <div><label for="sv">Service</label><select id="sv" name="service_id"><option value="">Tous</option>@foreach (\App\Models\Service::optionsParDirection() as $dn => $groupe)<optgroup label="{{ $dn }}">@foreach ($groupe as $id => $nom)<option value="{{ $id }}" @selected(($f['service_id'] ?? '') == $id)>{{ $nom }}</option>@endforeach</optgroup>@endforeach</select></div>
        @endif
        <div class="flex gap-2"><button class="btn btn-primaire">Filtrer</button><a class="btn btn-secondaire" href="{{ route('besoins.index') }}">×</a></div>
    </form>
    <x-carte sans-marge>
        @if ($besoins->isEmpty())
            <x-vide message="Aucune demande ne correspond à ces critères.">
                @can('create', \App\Models\BesoinFormation::class)<a class="btn btn-primaire" href="{{ route('besoins.create') }}">Nouvelle demande</a>@endcan
            </x-vide>
        @else
            @php $selection = auth()->user()->aUneDesPermissions('besoins.creer', 'besoins.instruire'); @endphp
            @if ($selection)<x-selection-suppression type="besoins" libelle="demandes" />@endif
            <div class="overflow-x-auto"><table>
                <thead><tr>@if ($selection)<th class="w-8 no-print"><span class="sr-only">Sélection</span></th>@endif<th>Référence</th><th>Formation souhaitée</th><th>Service</th><th class="text-right">Participants</th><th>Statut</th><th>Envoyée le</th></tr></thead>
                <tbody>@foreach ($besoins as $b)
                    <tr>
                        @if ($selection)<td class="no-print"><input type="checkbox" form="selection-besoins" name="ids[]" value="{{ $b->id }}" class="sel-besoins" aria-label="Sélectionner {{ $b->reference }}"></td>@endif
                        <td class="font-medium tabular-nums whitespace-nowrap"><a href="{{ route('besoins.show', $b) }}">{{ $b->reference }}</a></td>
                        <td>{{ $b->intitule }}</td><td>{{ $b->service->nom }}</td>
                        <td class="text-right">{{ $b->nb_participants ?? ($b->agents_count ?: '—') }}</td><td>@php [$lib, $coul] = $b->statutAffiche(); @endphp<x-badge :texte="$lib" :couleur="$coul" /></td><td>{{ date_fr($b->soumis_le) }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $besoins->links() }}
        @endif
    </x-carte>
</x-layout>
