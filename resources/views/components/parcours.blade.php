{{-- Parcours d'une formation : étapes franchies, étape en cours, étapes à venir. Lu de gauche à droite (vertical sur mobile). --}}
@props(['etapes' => [], 'titre' => 'Parcours'])
<nav aria-label="{{ $titre }}" {{ $attributes->merge(['class' => 'carte mb-5 px-4 py-3']) }}>
    <ol class="parcours">
        @foreach ($etapes as $i => [$libelle, $etat])
            <li class="parcours-etape parcours-{{ $etat }}" @if ($etat === 'encours') aria-current="step" @endif>
                <span class="parcours-pastille" aria-hidden="true">@if ($etat === 'fait')<x-icone nom="valide" class="h-3.5 w-3.5" />@else{{ $i + 1 }}@endif</span>
                <span class="parcours-libelle">{{ $libelle }}<span class="sr-only"> — {{ ['fait' => 'terminé', 'encours' => 'étape actuelle', 'afaire' => 'à venir'][$etat] }}</span></span>
            </li>
        @endforeach
    </ol>
</nav>
