<x-layout titre="Formateurs">
    <x-entete titre="Formateurs internes et externes">@permission('organismes.gerer')<a class="btn btn-primaire" href="{{ route('formateurs.create') }}">Nouveau formateur</a>@endpermission</x-entete>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-4 items-end no-print">
        <div class="md:col-span-2"><label for="q">Nom ou domaine d'expertise</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div>
        <div><label for="ty">Type</label><select id="ty" name="type"><option value="">Tous</option><option value="interne" @selected(($f['type'] ?? '') === 'interne')>Interne</option><option value="externe" @selected(($f['type'] ?? '') === 'externe')>Externe</option></select></div>
        <div><button class="btn btn-primaire">Filtrer</button></div>
    </form>
    <x-carte sans-marge>
        @if ($formateurs->isEmpty())<x-vide message="Aucun formateur." />@else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Nom</th><th>Type</th><th>Organisme / matricule</th><th>Expertise</th><th>Contact</th><th class="text-right">Formations</th><th></th></tr></thead>
                <tbody>@foreach ($formateurs as $fm)
                    <tr><td>{{ $fm->nom_complet }}@unless ($fm->actif) <x-badge texte="Inactif" />@endunless</td><td>{{ ucfirst($fm->type) }}</td><td>{{ $fm->type === 'interne' ? $fm->agent?->matricule : $fm->organisme?->raison_sociale }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($fm->domaines_expertise, 80) }}</td><td>{{ $fm->email }} {{ $fm->telephone }}</td><td class="text-right">{{ $fm->sessions_count }}</td>
                        <td>@permission('organismes.gerer')<a class="btn btn-secondaire btn-petit" href="{{ route('formateurs.edit', $fm) }}">Modifier</a>@endpermission</td></tr>
                @endforeach</tbody>
            </table></div>
            {{ $formateurs->links() }}
        @endif
    </x-carte>
</x-layout>
