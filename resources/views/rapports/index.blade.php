<x-layout titre="Rapports">
    <x-entete titre="Rapports et statistiques" sous-titre="Rapports filtrables, imprimables et exportables (PDF, Excel)" />
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($rapports as $code => $titre)
            <a href="{{ route('rapports.show', [$code, 'annee' => $annee]) }}" class="carte p-4 no-underline hover:border-primaire hover:no-underline"><span class="font-medium text-slate-900">{{ $titre }}</span></a>
        @endforeach
    </div>
</x-layout>
