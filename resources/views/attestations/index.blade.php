<x-layout titre="Attestations">
    <x-entete titre="Attestations émises" />
    <form method="GET" class="carte p-3 mb-4 flex gap-2 items-end no-print"><div class="flex-1"><label for="q">Référence ou matricule</label><input id="q" type="search" name="q" value="{{ $q }}"></div><button class="btn btn-primaire">Rechercher</button></form>
    <x-carte sans-marge>
        @if ($attestations->isEmpty())
            <x-vide message="Aucune attestation." />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Référence</th><th>Agent</th><th>Formation</th><th>Émise le</th><th>Par</th><th>État</th><th>Imprimée</th><th></th></tr></thead>
                <tbody>@foreach ($attestations as $a)
                    <tr><td class="font-medium tabular-nums">{{ $a->reference }}</td><td>{{ $a->inscription->agent->nom_complet }}</td><td>{{ $a->inscription->session->action->intitule }}</td><td>{{ date_fr($a->emise_le) }}</td><td>{{ $a->emetteur->name }}</td>
                        <td>@if ($a->estValide())<x-badge texte="Valide" couleur="vert" />@else<x-badge texte="Annulée" couleur="rouge" /> <span class="text-xs">{{ $a->motif_annulation }}</span>@endif</td>
                        @php $imp = $impressions[$a->id] ?? null; @endphp
                        <td class="whitespace-nowrap" title="Nombre de téléchargements du PDF (chaque téléchargement est inscrit au journal d'audit)">@if ($imp){{ $imp['nombre'] }} fois<div class="text-xs text-encre-3">dernière le {{ date_fr($imp['derniere']) }}</div>@else<span class="text-encre-3">Jamais</span>@endif</td>
                        <td class="whitespace-nowrap">@if ($a->estValide())<a class="btn btn-secondaire btn-petit" href="{{ route('attestations.pdf', $a) }}">PDF</a>
                            <x-dialogue-motif :id="'ann-'.$a->id" :url="route('attestations.annuler', $a)" titre="Annuler l'attestation" bouton="Annuler" libelle="Annuler l'attestation" />@endif</td></tr>
                @endforeach</tbody>
            </table></div>
            {{ $attestations->links() }}
        @endif
    </x-carte>
</x-layout>
