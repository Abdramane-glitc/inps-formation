@props(['message' => 'Aucun élément à afficher.'])
<div class="flex flex-col items-center justify-center gap-2 py-12 px-6 text-center">
    <x-icone nom="vide" class="h-8 w-8 text-filet-fort" />
    <p class="text-sm text-encre-2 max-w-md">{{ $message }}</p>
    @if (trim($slot))<div class="mt-2">{{ $slot }}</div>@endif
</div>
