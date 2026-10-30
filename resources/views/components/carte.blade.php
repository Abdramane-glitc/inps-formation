{{-- Panneau blanc. « icone » (2.6.0) : petit symbole devant le titre. Fente « actions » : boutons à droite du titre. --}}
@props(['titre' => null, 'icone' => null])
<section {{ $attributes->except(['sans-marge', 'icone'])->merge(['class' => 'carte']) }}>
    @if ($titre || isset($actions))
        <div class="carte-entete">
            <h2 class="carte-titre">@if ($icone)<x-icone :nom="$icone" class="h-[17px] w-[17px]" />@endif{{ $titre }}</h2>
            @isset($actions)<div class="flex flex-wrap items-center gap-2 no-print">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="{{ $attributes->has('sans-marge') ? '' : 'carte-corps' }}">{{ $slot }}</div>
</section>
