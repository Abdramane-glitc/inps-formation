@php use App\Models\FormationDiplomante as D; @endphp
<x-layout titre="Formations diplômantes">
    <x-entete titre="Formations diplômantes" sous-titre="Licences, masters, doctorats… suivis par les agents dans un établissement, en dehors des formations organisées par l'INPS.">
        <a class="btn btn-secondaire" href="{{ route('diplomantes.export', request()->query()) }}" data-sans-chargement>Exporter (Excel)</a>
        <a class="btn btn-primaire" href="{{ route('diplomantes.create') }}">Nouvelle formation diplômante</a>
    </x-entete>

    <div class="grid gap-3 grid-cols-2 md:grid-cols-4 mb-4">
        @foreach (D::SITUATIONS as $k => $l)
            <a href="{{ route('diplomantes.index', ['situation' => $k]) }}" class="carte p-3 block no-underline hover:border-inps">
                <span class="block text-2xl font-bold text-encre">{{ $compte[$k] ?? 0 }}</span><span class="text-sm text-encre-2">{{ $l }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-6 items-end no-print">
        <div class="md:col-span-2"><label for="q">Agent, matricule, intitulé ou établissement</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}"></div>
        <div><label for="niv">Niveau</label><select id="niv" name="niveau"><option value="">Tous</option>@foreach (D::NIVEAUX as $k => $l)<option value="{{ $k }}" @selected(($f['niveau'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="sit">Situation</label><select id="sit" name="situation"><option value="">Toutes</option>@foreach (D::SITUATIONS as $k => $l)<option value="{{ $k }}" @selected(($f['situation'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="pec">Prise en charge</label><select id="pec" name="prise_en_charge"><option value="">Toutes</option>@foreach (D::PRISES_EN_CHARGE as $k => $l)<option value="{{ $k }}" @selected(($f['prise_en_charge'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="an">Année académique</label><select id="an" name="annee"><option value="">Toutes</option>@foreach ($annees as $a)<option value="{{ $a }}" @selected((int) ($f['annee'] ?? 0) === (int) $a)>{{ $a }}-{{ $a + 1 }}</option>@endforeach</select></div>
        <div class="md:col-span-2"><label for="dir">Direction de l'agent</label><select id="dir" name="direction_id"><option value="">Toutes</option>@foreach ($directions as $d)<option value="{{ $d->id }}" @selected((int) ($f['direction_id'] ?? 0) === $d->id)>{{ $d->nom }}</option>@endforeach</select></div>
        <div class="flex gap-2"><button class="btn btn-primaire">Filtrer</button>@if ($f)<a class="btn btn-secondaire" href="{{ route('diplomantes.index') }}">Effacer</a>@endif</div>
    </form>

    <x-carte sans-marge>
        @if ($diplomantes->isEmpty())
            <x-vide :message="$f ? 'Aucune formation diplômante ne correspond à ces filtres.' : 'Aucune formation diplômante enregistrée. Utilisez « Nouvelle formation diplômante » pour en ajouter une.'" />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Agent</th><th>Diplôme préparé</th><th>Établissement</th><th>Année</th><th>Prise en charge</th><th>Situation</th></tr></thead>
                <tbody>@foreach ($diplomantes as $d)
                    <tr>
                        <td><a href="{{ route('diplomantes.show', $d) }}">{{ $d->agent->nom_complet }}</a><div class="text-xs text-encre-3">{{ $d->agent->matricule }}{{ $d->agent->service ? ' — '.$d->agent->service->nom : '' }}</div></td>
                        <td><strong>{{ $d->libelle('niveau') }}</strong> — {{ $d->intitule }}</td>
                        <td>{{ $d->etablissement }}@if ($d->ville || $d->pays)<div class="text-xs text-encre-3">{{ collect([$d->ville, $d->pays])->filter()->implode(', ') }}</div>@endif</td>
                        <td class="whitespace-nowrap">{{ $d->annee_academique }}</td>
                        <td>{{ $d->libelle('prise_en_charge') }}</td>
                        <td><x-badge :texte="$d->libelle('situation')" :couleur="D::COULEURS[$d->situation] ?? 'gris'" />@if ($d->date_obtention)<div class="text-xs text-encre-3">le {{ date_fr($d->date_obtention) }}</div>@endif</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $diplomantes->links() }}
        @endif
    </x-carte>
</x-layout>
