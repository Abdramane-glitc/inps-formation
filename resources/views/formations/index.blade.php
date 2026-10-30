<x-layout titre="Formations">
    <x-entete titre="Formations" :sous-titre="$limite ? 'Les formations demandées par votre service ou suivies par ses agents.' : 'Trois étapes pour chaque formation : la formation, ses participants, les attestations.'">
        @permission('actions.gerer')<a class="btn btn-primaire" href="{{ route('formations.create') }}">Nouvelle formation</a>@endpermission
    </x-entete>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-7 items-end no-print">
        <div class="md:col-span-2"><label for="q">Recherche</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Intitulé ou référence"></div>
        <div><label for="an">Année</label><select id="an" name="annee"><option value="">Toutes</option>@foreach ($annees as $an)<option @selected(($f['annee'] ?? '') == $an)>{{ $an }}</option>@endforeach</select></div>
        <div><label for="st">Statut</label><select id="st" name="statut"><option value="">Tous</option>@foreach (['planifiee' => 'Programmée', 'a_terminer' => 'À terminer (dates passées)', 'terminee' => 'Terminée', 'annulee' => 'Annulée'] as $k => $l)<option value="{{ $k }}" @selected(($f['statut'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="ty">Type</label><select id="ty" name="type"><option value="">Tous</option>@foreach (\App\Models\ActionFormation::TYPES as $k => $l)<option value="{{ $k }}" @selected(($f['type'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="dm">Domaine</label><select id="dm" name="domaine_id"><option value="">Tous</option>@foreach ($domaines as $d)<option value="{{ $d->id }}" @selected(($f['domaine_id'] ?? '') == $d->id)>{{ $d->nom }}</option>@endforeach</select></div>
        <div class="flex gap-2"><button class="btn btn-primaire">Filtrer</button><a class="btn btn-secondaire" href="{{ route('formations.index') }}" aria-label="Effacer les filtres">×</a></div>
    </form>
    <x-carte sans-marge>
        @if ($formations->isEmpty())
            <x-vide message="Aucune formation ne correspond à ces critères.">
                @permission('actions.gerer')<p class="text-sm text-encre-2">Une formation est créée quand la RH accepte une demande, ou directement avec « Nouvelle formation ».</p>@endpermission
            </x-vide>
        @else
            @php $selection = auth()->user()->aPermission('actions.gerer'); @endphp
            @if ($selection)<x-selection-suppression type="formations" libelle="formations" />@endif
            <div class="overflow-x-auto"><table>
                <thead><tr>@if ($selection)<th class="w-8 no-print"><span class="sr-only">Sélection</span></th>@endif<th>Référence</th><th>Intitulé</th><th>Dates</th><th>Lieu</th><th class="text-right">{{ $limite ? 'Agents de votre service' : 'Participants' }}</th><th>Demande</th><th>Statut</th></tr></thead>
                <tbody>@foreach ($formations as $a)
                    @php $s = $a->session; @endphp
                    <tr>
                        @if ($selection)<td class="no-print"><input type="checkbox" form="selection-formations" name="ids[]" value="{{ $a->id }}" class="sel-formations" aria-label="Sélectionner {{ $a->reference }}"></td>@endif
                        <td class="font-medium tabular-nums whitespace-nowrap"><a href="{{ route('formations.show', $a) }}">{{ $a->reference }}</a></td>
                        <td>{{ $a->intitule }}@if ($a->historique_importe) <x-badge texte="Historique" /> @endif
                            <div class="text-xs text-encre-3">{{ \App\Models\ActionFormation::TYPES[$a->type] }}@if ($a->organisme) — {{ $a->organisme->raison_sociale }}@endif</div></td>
                        <td class="whitespace-nowrap">@if ($s){{ date_fr($s->date_debut) }} – {{ date_fr($s->date_fin) }}@else<span class="text-encre-3">à fixer</span>@endif</td>
                        <td>{{ $s?->lieu ?? '—' }}</td>
                        <td class="text-right tabular-nums">@if ($s){{ $s->occupees }}@else — @endif</td>
                        <td class="whitespace-nowrap">{{ $a->besoin?->reference ?? '—' }}</td>
                        <td>@if ($s)<x-badge :statut="$s->statut" />@if ($s->estATerminer()) <x-badge texte="À terminer" couleur="orange" />@endif @else<x-badge :statut="$a->statut" />@endif</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $formations->links() }}
        @endif
    </x-carte>
</x-layout>
