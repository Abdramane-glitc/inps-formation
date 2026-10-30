<x-layout titre="Corbeille">
    <x-entete titre="Corbeille" sous-titre="Les demandes et formations supprimées. Elles ne sont plus visibles ailleurs dans l'application ; « Restaurer » les remet en place." />

    <x-carte :titre="'Formations supprimées ('.$formations->count().')'" sans-marge class="mb-5">
        @if ($formations->isEmpty())<x-vide message="Aucune formation supprimée." />@else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Référence</th><th>Intitulé</th><th>Dates</th><th>Supprimée le</th><th>Par</th><th class="no-print"></th></tr></thead>
                <tbody>@foreach ($formations as $a)
                    <tr><td class="font-medium tabular-nums whitespace-nowrap">{{ $a->reference }}</td><td>{{ $a->intitule }}</td>
                        <td class="whitespace-nowrap">@if ($a->session){{ date_fr($a->session->date_debut) }} – {{ date_fr($a->session->date_fin) }}@else — @endif</td>
                        <td class="whitespace-nowrap">{{ date_fr($a->deleted_at, true) }}</td><td>{{ $parFormation[$a->id]?->user?->name ?? '—' }}</td>
                        <td class="text-right no-print"><x-action-form :url="route('corbeille.restaurer', ['formations', $a->id])" libelle="Restaurer" confirmer="Restaurer la formation {{ $a->reference }} et réinscrire ses participants ?" /></td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </x-carte>

    <x-carte :titre="'Demandes supprimées ('.$besoins->count().')'" sans-marge>
        @if ($besoins->isEmpty())<x-vide message="Aucune demande supprimée." />@else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Référence</th><th>Formation souhaitée</th><th>Service</th><th>Supprimée le</th><th>Par</th><th class="no-print"></th></tr></thead>
                <tbody>@foreach ($besoins as $b)
                    <tr><td class="font-medium tabular-nums whitespace-nowrap">{{ $b->reference }}</td><td>{{ $b->intitule }}</td><td>{{ $b->service?->nom }}</td>
                        <td class="whitespace-nowrap">{{ date_fr($b->deleted_at, true) }}</td><td>{{ $parBesoin[$b->id]?->user?->name ?? '—' }}</td>
                        <td class="text-right no-print"><x-action-form :url="route('corbeille.restaurer', ['besoins', $b->id])" libelle="Restaurer" /></td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </x-carte>
    <p class="aide mt-4">Les agents et les comptes supprimés ne passent pas par la corbeille : ils ne peuvent être supprimés que s'ils n'ont jamais servi, et il suffit de les recréer.</p>
</x-layout>
