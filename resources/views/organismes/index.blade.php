<x-layout titre="Organismes">
    <x-entete titre="Organismes de formation">@permission('organismes.gerer')<a class="btn btn-primaire" href="{{ route('organismes.create') }}">Nouvel organisme</a>@endpermission</x-entete>
    <form method="GET" class="carte p-3 mb-4 flex gap-2 items-end no-print"><div class="flex-1"><label for="q">Recherche</label><input id="q" type="search" name="q" value="{{ $q }}"></div><button class="btn btn-primaire">Rechercher</button></form>
    <x-carte sans-marge>
        @if ($organismes->isEmpty())
            <x-vide message="Aucun organisme enregistré." />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Raison sociale</th><th>Sigle</th><th>Téléphone</th><th>E-mail</th><th class="text-right">Actions</th><th class="text-right">Formateurs</th><th class="text-right">Appréciation</th><th>Statut</th></tr></thead>
                <tbody>@foreach ($organismes as $o)
                    <tr><td><a href="{{ route('organismes.show', $o) }}">{{ $o->raison_sociale }}</a></td><td>{{ $o->sigle }}</td><td>{{ $o->telephone }}</td><td>{{ $o->email }}</td><td class="text-right">{{ $o->actions_count }}</td><td class="text-right">{{ $o->formateurs_count }}</td>
                        <td class="text-right">{{ $o->appreciations_avg_note ? number_format($o->appreciations_avg_note, 1, ',', ' ').' / 5' : '—' }}</td><td>{{ $o->actif ? 'Actif' : 'Inactif' }}</td></tr>
                @endforeach</tbody>
            </table></div>
            {{ $organismes->links() }}
        @endif
    </x-carte>
</x-layout>
