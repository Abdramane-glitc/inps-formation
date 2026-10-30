{{-- Formations diplômantes d'un agent (fiche agent pour la RH, « Mon espace » pour l'agent). $liens : lien vers la fiche (RH). --}}
@php use App\Models\FormationDiplomante as D; @endphp
<div class="overflow-x-auto"><table>
    <thead><tr><th>Diplôme préparé</th><th>Établissement</th><th>Année</th><th>Situation</th></tr></thead>
    <tbody>@foreach ($liste as $d)
        <tr>
            <td>@if ($liens)<a href="{{ route('diplomantes.show', $d) }}">{{ $d->libelle('niveau') }}</a>@else<strong>{{ $d->libelle('niveau') }}</strong>@endif — {{ $d->intitule }}</td>
            <td>{{ $d->etablissement }}</td>
            <td class="whitespace-nowrap">{{ $d->annee_academique }}</td>
            <td><x-badge :texte="$d->libelle('situation')" :couleur="D::COULEURS[$d->situation] ?? 'gris'" />@if ($d->date_obtention) <span class="text-xs text-encre-3">le {{ date_fr($d->date_obtention) }}</span>@endif</td>
        </tr>
    @endforeach</tbody>
</table></div>
