{{--
    Carte chiffrée. « evolution » (2.6.0) : variation en % par rapport à l'année précédente (null = non calculable),
    affichée en vert (hausse), rouge (baisse) ou gris (stable). « icone » : petit symbole devant le libellé.
--}}
@props(['libelle', 'valeur', 'detail' => null, 'lien' => null, 'icone' => null, 'evolution' => null])
<div class="indicateur">
    <div class="indicateur-libelle">@if ($icone)<x-icone :nom="$icone" class="h-4 w-4" />@endif{{ $libelle }}</div>
    <div class="flex flex-wrap items-center gap-2.5">
        <div class="indicateur-valeur">@if ($lien)<a href="{{ $lien }}" class="text-encre hover:text-inps no-underline">{{ $valeur }}</a>@else{{ $valeur }}@endif</div>
        @if ($evolution !== null)
            @php $sens = $evolution > 0 ? 'hausse' : ($evolution < 0 ? 'baisse' : 'stable'); @endphp
            <span class="evolution evolution-{{ $sens }} mt-2" title="Par rapport à l'année précédente">{{ $evolution > 0 ? '+' : '' }}{{ str_replace('.', ',', (string) $evolution) }} %<span aria-hidden="true">{{ ['hausse' => ' ↗', 'baisse' => ' ↘', 'stable' => ''][$sens] }}</span></span>
        @endif
    </div>
    @if ($detail)<div class="mt-1 text-xs text-encre-3">{{ $detail }}</div>@endif
</div>
