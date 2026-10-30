{{-- En-tête institutionnel configurable (aucun logo officiel n'est fourni par défaut). --}}
<table class="entete">
    <tr>
        <td style="width: 70px">@if ($institution['logo_data'])<img src="{{ $institution['logo_data'] }}" style="height: 55px" alt="">@endif</td>
        <td>
            <div style="font-weight: bold; font-size: 11pt">{{ $institution['nom'] }} ({{ $institution['sigle'] }})</div>
            <div>{{ $institution['direction'] }}</div>
            @if ($institution['adresse'])<div class="petit">{{ $institution['adresse'] }}@if ($institution['telephone']) — Tél. {{ $institution['telephone'] }}@endif</div>@endif
            @if ($institution['mention_demo'])<div class="demo">{{ $institution['mention_demo'] }}</div>@endif
        </td>
        <td class="droite petit" style="width: 130px">Édité le {{ now()->format('d/m/Y à H:i') }}</td>
    </tr>
</table>
@isset($titre)<h1>{{ $titre }}</h1>@endisset
