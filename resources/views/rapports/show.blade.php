@php $q = request()->query(); @endphp
<x-layout :titre="$r['titre']">
    <x-entete :titre="$r['titre']">
        <a class="btn btn-secondaire" href="{{ route('rapports.index') }}">Tous les rapports</a>
        <a class="btn btn-secondaire" href="{{ route('rapports.export', [$code, 'xlsx'] + $q) }}">Excel</a>
        <a class="btn btn-secondaire" href="{{ route('rapports.export', [$code, 'pdf'] + $q) }}">PDF</a>
    </x-entete>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-7 items-end no-print">
        <div><label for="an">Année</label><input id="an" type="number" name="annee" value="{{ $f['annee'] ?? '' }}" min="2000" max="2100"></div>
        <div><label for="dir">Direction</label><select id="dir" name="direction_id"><option value="">Toutes</option>@foreach ($directions as $d)<option value="{{ $d->id }}" @selected(($f['direction_id'] ?? '') == $d->id)>{{ $d->nom }}</option>@endforeach</select></div>
        <div><label for="srv">Service</label><select id="srv" name="service_id"><option value="">Tous</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(($f['service_id'] ?? '') == $s->id)>{{ $s->nom }}</option>@endforeach</select></div>
        <div><label for="dom">Domaine</label><select id="dom" name="domaine_id"><option value="">Tous</option>@foreach ($domaines as $d)<option value="{{ $d->id }}" @selected(($f['domaine_id'] ?? '') == $d->id)>{{ $d->nom }}</option>@endforeach</select></div>
        <div><label for="ty">Type</label><select id="ty" name="type"><option value="">Tous</option><option value="interne" @selected(($f['type'] ?? '') === 'interne')>Interne</option><option value="externe" @selected(($f['type'] ?? '') === 'externe')>Externe</option><option value="mixte" @selected(($f['type'] ?? '') === 'mixte')>Mixte</option></select></div>
        @if ($code === 'agents_sans_formation')
            <div><label for="du">Du</label><input id="du" type="date" name="du" value="{{ $f['du'] ?? '' }}"></div>
            <div><label for="au">Au</label><input id="au" type="date" name="au" value="{{ $f['au'] ?? '' }}"></div>
        @endif
        <div><button class="btn btn-primaire">Actualiser</button></div>
    </form>
    <div class="hidden print:block mb-3 text-sm">{{ $institution['nom'] }} — {{ $institution['direction'] }} — édité le {{ now()->format('d/m/Y H:i') }}</div>
    <x-carte sans-marge>
        @if (empty($r['lignes']))
            <x-vide message="Aucune donnée pour ces filtres." />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr>@foreach ($r['colonnes'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                <tbody>@foreach ($r['lignes'] as $l)<tr>@foreach (array_values($l) as $k => $v)<td class="{{ is_numeric($v) ? 'text-right tabular-nums' : '' }}">{{ in_array($k, $r['montants'], true) && is_numeric($v) ? fcfa($v) : (is_float($v) ? number_format($v, 1, ',', ' ') : ($v ?? '—')) }}</td>@endforeach</tr>@endforeach</tbody>
            </table></div>
        @endif
        <p class="px-4 py-3 text-xs text-slate-500 border-t border-slate-100">Règle de calcul : {{ $r['note'] }}</p>
    </x-carte>
</x-layout>
