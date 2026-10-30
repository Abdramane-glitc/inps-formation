{{-- En-tête de page : titre, sous-titre et actions à droite (style 2.6.0, sans filet). --}}
@props(['titre', 'sousTitre' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
    <div class="min-w-0">
        <h1>{{ $titre }}</h1>
        @if ($sousTitre)<p class="mt-1 text-[0.92rem] text-encre-2 max-w-3xl">{{ $sousTitre }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap items-center gap-2 no-print">{{ $slot }}</div>@endif
</div>
