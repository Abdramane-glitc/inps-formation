@php $onglets = ['a_faire' => 'À faire', 'a_venir' => 'À venir', 'faites' => 'Faites']; @endphp
<x-layout titre="Évaluations">
    <x-entete titre="Appliquent-ils ce qu'ils ont appris ?" sous-titre="3 à 6 mois après une formation, indiquez pour chaque agent formé s'il applique ce qu'il a appris." />
    <nav class="flex flex-wrap gap-2 mb-4" aria-label="État des évaluations">
        @foreach ($onglets as $k => $lib)
            <a href="{{ route('evaluations.index', ['etat' => $k]) }}" class="btn {{ $etat === $k ? 'btn-primaire' : 'btn-secondaire' }}" @if ($etat === $k) aria-current="page" @endif>{{ $lib }} ({{ $compteurs[$k] }})</a>
        @endforeach
    </nav>
    <x-carte sans-marge>
        @if ($liste->isEmpty())
            <x-vide :message="match ($etat) { 'a_faire' => 'Aucune évaluation à faire pour l\'instant.', 'a_venir' => 'Aucune évaluation à venir.', default => 'Aucune évaluation faite.' }" />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Formation</th><th>Terminée le</th><th>À évaluer à partir du</th><th>Agents</th><th></th></tr></thead>
                <tbody>@foreach ($liste as $x)
                    <tr>
                        <td>{{ $x->s->action->intitule }}<div class="text-xs text-encre-3">{{ $x->s->action->reference }}</div></td>
                        <td>{{ date_fr($x->s->date_fin) }}</td>
                        <td>{{ date_fr($x->a_partir_du) }}</td>
                        <td>{{ $x->restants ? $x->restants.' à évaluer sur '.$x->agents : $x->agents.' évalué(s)' }}</td>
                        <td class="whitespace-nowrap"><a class="btn {{ $etat === 'a_faire' ? 'btn-primaire' : 'btn-secondaire' }} btn-petit" href="{{ route('evaluations.froid', $x->s->action_id) }}">{{ match ($etat) { 'a_faire' => 'Évaluer', 'faites' => 'Voir ou modifier', default => 'Voir' } }}</a></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </x-carte>
</x-layout>
