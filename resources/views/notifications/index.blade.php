<x-layout titre="Notifications">
    <x-entete titre="Notifications">
        <x-action-form :url="route('notifications.tout-lire')" libelle="Tout marquer comme lu" />
    </x-entete>
    <x-carte sans-marge>
        @forelse ($notifications as $n)
            <form method="POST" action="{{ route('notifications.lue', $n->id) }}" class="border-b border-slate-100">
                @csrf
                <button class="w-full text-left px-4 py-3 hover:bg-slate-50 cursor-pointer {{ $n->read_at ? 'text-slate-500' : '' }}">
                    <span class="flex justify-between gap-3">
                        <span class="font-medium {{ $n->read_at ? '' : 'text-slate-900' }}">@unless ($n->read_at)<span class="inline-block h-2 w-2 rounded-full bg-primaire mr-1" aria-label="Non lue"></span>@endunless{{ $n->data['titre'] ?? 'Notification' }}</span>
                        <span class="text-xs text-slate-500 whitespace-nowrap">{{ date_fr($n->created_at, true) }}</span>
                    </span>
                    <span class="block text-sm">{{ $n->data['message'] ?? '' }}</span>
                    <span class="block text-xs text-slate-400">{{ \App\Services\NotificationService::CATEGORIES[$n->data['categorie'] ?? ''] ?? '' }}</span>
                </button>
            </form>
        @empty
            <x-vide message="Aucune notification." />
        @endforelse
        {{ $notifications->links() }}
    </x-carte>
</x-layout>
