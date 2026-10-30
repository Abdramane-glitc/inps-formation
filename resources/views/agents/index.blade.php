<x-layout titre="Personnel">
    <x-entete titre="Référentiel du personnel" :sous-titre="$tous ? 'Agents de l\'INPS (désactivation logique : aucune fiche n\'est supprimée)' : 'Agents de votre service'.($monService ? ' : '.$monService->nom : '')">
        @permission('agents.exporter')<a class="btn btn-secondaire" href="{{ route('agents.export') }}">Exporter (Excel)</a>@endpermission
        @permission('agents.importer')<a class="btn btn-secondaire" href="{{ route('imports.index') }}">Importer</a>@endpermission
        @permission('utilisateurs.gerer')<a class="btn btn-secondaire" href="{{ route('agents.acces') }}">Accès des agents</a>@endpermission
        @permission('agents.gerer')<a class="btn btn-primaire" href="{{ route('agents.create') }}">Nouvel agent</a>@endpermission
    </x-entete>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-6 items-end no-print">
        <div class="md:col-span-2"><label for="q">Recherche (matricule, nom, prénom, fonction)</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div>
        @if ($tous)
        <div><label for="dir">Direction</label><select id="dir" name="direction_id"><option value="">Toutes</option>@foreach ($directions as $d)<option value="{{ $d->id }}" @selected(($f['direction_id'] ?? '') == $d->id)>{{ $d->nom }}</option>@endforeach</select></div>
        <div><label for="srv">Service</label><select id="srv" name="service_id"><option value="">Tous</option>@foreach ($services->groupBy(fn ($s) => $s->direction?->nom ?? 'Sans direction')->sortKeys() as $dn => $groupe)<optgroup label="{{ $dn }}">@foreach ($groupe as $s)<option value="{{ $s->id }}" @selected(($f['service_id'] ?? '') == $s->id)>{{ $s->nom }}</option>@endforeach</optgroup>@endforeach</select></div>
        @endif
        <div><label for="act">Statut</label><select id="act" name="actif"><option value="1" @selected(($f['actif'] ?? '1') === '1')>Actifs</option><option value="0" @selected(($f['actif'] ?? '') === '0')>Inactifs</option><option value="tous" @selected(($f['actif'] ?? '') === 'tous')>Tous</option></select></div>
        <div class="flex gap-2"><select name="tri" aria-label="Tri"><option value="nom">Tri : nom</option><option value="matricule" @selected(($f['tri'] ?? '') === 'matricule')>Tri : matricule</option><option value="service" @selected(($f['tri'] ?? '') === 'service')>Tri : service</option></select><button class="btn btn-primaire">OK</button></div>
    </form>
    <x-carte sans-marge>
        @if ($agents->isEmpty())
            <x-vide message="Aucun agent ne correspond à ces critères." />
        @else
            @php $selection = auth()->user()->aPermission('agents.gerer'); @endphp
            @if ($selection)<x-selection-suppression type="agents" libelle="agents" />@endif
            <div class="overflow-x-auto"><table>
                <thead><tr>@if ($selection)<th class="w-8 no-print"><span class="sr-only">Sélection</span></th>@endif<th>Matricule</th><th>Nom et prénom</th><th>Direction</th><th>Service</th><th>Fonction</th><th>Statut</th></tr></thead>
                <tbody>
                @foreach ($agents as $a)
                    <tr>
                        @if ($selection)<td class="no-print"><input type="checkbox" form="selection-agents" name="ids[]" value="{{ $a->id }}" class="sel-agents" aria-label="Sélectionner {{ $a->matricule }}"></td>@endif
                        <td class="font-medium tabular-nums"><a href="{{ route('agents.show', $a) }}">{{ $a->matricule }}</a></td>
                        <td>{{ $a->nom_complet }}</td><td>{{ $a->direction?->nom }}</td><td>{{ $a->service?->nom }}</td><td>{{ $a->fonction }}</td>
                        <td>@if ($a->actif)<x-badge texte="Actif" couleur="vert" />@else<x-badge texte="Inactif" couleur="gris" />@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            {{ $agents->links() }}
        @endif
    </x-carte>
</x-layout>
