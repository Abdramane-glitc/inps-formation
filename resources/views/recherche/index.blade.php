{{-- Résultats de la recherche générale (2.6.0) : seulement ce que le compte a le droit de voir. --}}
<x-layout titre="Recherche">
    <x-entete titre="Recherche" :sous-titre="$q !== '' ? 'Résultats pour « '.$q.' »' : 'Tapez au moins deux caractères dans la barre de recherche.'" />
    @if (mb_strlen($q) >= 2 && empty($resultats))
        <x-carte><x-vide :message="'Aucun résultat pour « '.$q.' ». Vérifiez l\'orthographe ou essayez un matricule, une référence ou une partie du nom.'" /></x-carte>
    @endif
    <div class="grid gap-5 lg:grid-cols-2 items-start">
        @foreach ($resultats as $rubrique => $lignes)
            <x-carte :titre="$rubrique.' ('.$lignes->count().')'" sans-marge>
                <ul class="divide-y divide-filet">
                    @foreach ($lignes as [$titre, $detail, $url])
                        <li><a href="{{ $url }}" class="flex flex-col px-5 py-3 hover:bg-fond-2 no-underline hover:no-underline">
                            <span class="font-medium text-encre">{{ $titre }}</span>
                            @if ($detail)<span class="text-xs text-encre-3 mt-0.5">{{ $detail }}</span>@endif
                        </a></li>
                    @endforeach
                </ul>
            </x-carte>
        @endforeach
    </div>
</x-layout>
