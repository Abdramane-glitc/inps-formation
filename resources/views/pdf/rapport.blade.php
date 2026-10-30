<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._pied')
@include('pdf._entete', ['titre' => $r['titre']])
@if (empty($r['lignes']))
    <p>Aucune donnée pour les filtres sélectionnés.</p>
@else
<table>
    <thead><tr>@foreach ($r['colonnes'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
    <tbody>@foreach ($r['lignes'] as $l)<tr>@foreach (array_values($l) as $k => $v)<td class="{{ is_numeric($v) ? 'droite' : '' }}">{{ in_array($k, $r['montants'] ?? [], true) && is_numeric($v) ? fcfa($v, false) : (is_float($v) ? number_format($v, 1, ',', ' ') : $v) }}</td>@endforeach</tr>@endforeach</tbody>
</table>
@endif
<p class="petit" style="margin-top: 8px">{{ $r['note'] }} @if (! empty($r['montants']))Montants en FCFA.@endif</p>
</body></html>
