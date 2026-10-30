@php
    $onglets = ['directions' => 'Directions', 'services' => 'Services', 'domaines' => 'Domaines', 'annees' => 'Années'] + $types;
    $estNom = in_array($type, array_keys($types), true);
@endphp
<x-layout :titre="$titre">
    <x-entete titre="Référentiels" sous-titre="Un élément utilisé se désactive (décocher « Actif ») pour préserver l'historique ; seul un élément utilisé nulle part peut être supprimé." />
    <nav class="flex flex-wrap gap-2 mb-4 no-print" aria-label="Référentiels">
        @foreach ($onglets as $k => $l)<a class="btn btn-secondaire btn-petit {{ $type === $k ? 'ring-2 ring-primaire' : '' }}" href="{{ route('admin.referentiels.index', $k) }}">{{ $l }}</a>@endforeach
    </nav>
    <x-carte :titre="$titre" sans-marge>
        <div class="overflow-x-auto"><table>
            <thead><tr>
                @if ($type === 'annees')<th>Année</th><th>Demandes ouvertes</th>
                @elseif ($estNom)<th>Code</th><th>Libellé</th><th>Ordre</th><th>Actif</th>
                @else<th>Code</th><th>Nom</th>@if ($type === 'directions')<th>Sigle</th>@endif @if ($type === 'services')<th>Direction</th>@endif @if ($type === 'domaines')<th>Description</th>@endif<th>Actif</th>@endif
                <th></th></tr></thead>
            <tbody>
            @foreach ($elements as $e)
                <tr>
                    @if ($type === 'annees')
                        <td><input form="f{{ $e->id }}" type="number" name="annee" value="{{ $e->annee }}" aria-label="Année"></td>
                        <td><input form="f{{ $e->id }}" type="checkbox" name="ouverte" value="1" @checked($e->statut === 'ouverte') aria-label="Demandes ouvertes"></td>
                    @elseif ($estNom)
                        <td><input form="f{{ $e->id }}" type="text" name="code" value="{{ $e->code }}" aria-label="Code"></td><td><input form="f{{ $e->id }}" type="text" name="libelle" value="{{ $e->libelle }}" aria-label="Libellé"></td>
                        <td><input form="f{{ $e->id }}" type="number" name="ordre" value="{{ $e->ordre }}" class="w-20" aria-label="Ordre"></td><td><input form="f{{ $e->id }}" type="checkbox" name="actif" value="1" @checked($e->actif) aria-label="Actif"></td>
                    @else
                        <td><input form="f{{ $e->id }}" type="text" name="code" value="{{ $e->code }}" aria-label="Code"></td><td><input form="f{{ $e->id }}" type="text" name="nom" value="{{ $e->nom }}" aria-label="Nom"></td>
                        @if ($type === 'directions')<td><input form="f{{ $e->id }}" type="text" name="sigle" value="{{ $e->sigle }}" class="w-32" aria-label="Sigle"></td>@endif
                        @if ($type === 'services')<td><select form="f{{ $e->id }}" name="direction_id" aria-label="Direction">@foreach ($directions as $d)<option value="{{ $d->id }}" @selected($e->direction_id === $d->id)>{{ $d->nom }}</option>@endforeach</select></td>@endif
                        @if ($type === 'domaines')<td><input form="f{{ $e->id }}" type="text" name="description" value="{{ $e->description }}" aria-label="Description"></td>@endif
                        <td><input form="f{{ $e->id }}" type="checkbox" name="actif" value="1" @checked($e->actif) aria-label="Actif"></td>
                    @endif
                    <td class="whitespace-nowrap"><button form="f{{ $e->id }}" class="btn btn-secondaire btn-petit">Enregistrer</button>
                        @if (empty($usages[$e->id]))
                            <x-action-form :url="route('admin.referentiels.destroy', [$type, $e->id])" methode="DELETE" libelle="Supprimer" classe="btn btn-danger btn-petit" confirmer="Supprimer définitivement « {{ $e->nom ?? $e->libelle ?? $e->annee }} » ? Il n'est utilisé nulle part." />
                        @else
                            <span class="block text-xs text-encre-3 mt-1" title="Utilisé : ne peut pas être supprimé, seulement désactivé">Utilisé : {{ \App\Support\Utilisations::texte($usages[$e->id]) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="bg-slate-50">
                @if ($type === 'annees')
                    <td><input form="fnouveau" type="number" name="annee" placeholder="Année" required aria-label="Nouvelle année"></td><td><input form="fnouveau" type="checkbox" name="ouverte" value="1" checked aria-label="Demandes ouvertes"></td>
                @elseif ($estNom)
                    <td><input form="fnouveau" type="text" name="code" placeholder="code" required aria-label="Code"></td><td><input form="fnouveau" type="text" name="libelle" placeholder="Libellé" required aria-label="Libellé"></td><td><input form="fnouveau" type="number" name="ordre" class="w-20" aria-label="Ordre"></td><td><input form="fnouveau" type="checkbox" name="actif" value="1" checked aria-label="Actif"></td>
                @else
                    <td><input form="fnouveau" type="text" name="code" placeholder="CODE" required aria-label="Code"></td><td><input form="fnouveau" type="text" name="nom" placeholder="Nom" required aria-label="Nom"></td>
                    @if ($type === 'directions')<td><input form="fnouveau" type="text" name="sigle" placeholder="Sigle" class="w-32" aria-label="Sigle"></td>@endif
                    @if ($type === 'services')<td><select form="fnouveau" name="direction_id" required aria-label="Direction"><option value="">—</option>@foreach ($directions as $d)<option value="{{ $d->id }}">{{ $d->nom }}</option>@endforeach</select></td>@endif
                    @if ($type === 'domaines')<td><input form="fnouveau" type="text" name="description" aria-label="Description"></td>@endif
                    <td><input form="fnouveau" type="checkbox" name="actif" value="1" checked aria-label="Actif"></td>
                @endif
                <td><button form="fnouveau" class="btn btn-primaire btn-petit">Ajouter</button></td>
            </tr>
            </tbody>
        </table></div>
    </x-carte>
    @foreach ($elements as $e)
        <form id="f{{ $e->id }}" method="POST" action="{{ route('admin.referentiels.update', [$type, $e->id]) }}" class="hidden">@csrf @method('PUT')</form>
    @endforeach
    <form id="fnouveau" method="POST" action="{{ route('admin.referentiels.store', $type) }}" class="hidden">@csrf</form>
</x-layout>
