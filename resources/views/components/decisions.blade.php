@props(['decisions'])
<x-carte titre="Historique des décisions">
    @if ($decisions->isEmpty())
        <p class="text-sm text-slate-500">Aucune décision enregistrée.</p>
    @else
        <ol class="space-y-3 text-sm">
            @foreach ($decisions as $d)
                <li class="border-l-2 border-primaire/40 pl-3">
                    <div class="font-medium">{{ $d->libelle }}@if ($d->etape_ordre) (étape {{ $d->etape_ordre }})@endif</div>
                    <div class="text-slate-500">{{ date_fr($d->created_at, true) }} — {{ $d->user?->name }}</div>
                    @if ($d->commentaire)<p class="mt-1 whitespace-pre-line">{{ $d->commentaire }}</p>@endif
                </li>
            @endforeach
        </ol>
    @endif
</x-carte>
