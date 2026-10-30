@php use App\Support\JournalLisible; @endphp
<x-layout titre="Journal d'audit">
    <x-entete titre="Journal d'audit" sous-titre="Tout ce qui a été fait dans l'application, par qui et quand. Ce journal ne peut être ni modifié ni effacé.">
        @permission('audit.exporter')<a class="btn btn-secondaire" href="{{ route('admin.audit.export', request()->query()) }}">Exporter (Excel)</a>@endpermission
    </x-entete>
    <form method="GET" class="carte p-3 mb-4 grid gap-3 md:grid-cols-6 items-end no-print">
        <div class="md:col-span-2"><label for="ca">Type</label><select id="ca" name="categorie"><option value="">Tout</option>@foreach (JournalLisible::CATEGORIES as $code => [$libelle])<option value="{{ $code }}" @selected(($f['categorie'] ?? '') === $code)>{{ $libelle }}</option>@endforeach</select></div>
        <div><label for="pe">Personne</label><select id="pe" name="user_id"><option value="">Tout le monde</option>@foreach ($personnes as $p)<option value="{{ $p->id }}" @selected(($f['user_id'] ?? '') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div><label for="du">Du</label><input id="du" type="date" name="du" value="{{ $f['du'] ?? '' }}"></div>
        <div><label for="au">Au</label><input id="au" type="date" name="au" value="{{ $f['au'] ?? '' }}"></div>
        <div class="flex gap-2"><button class="btn btn-primaire">Filtrer</button><a class="btn btn-secondaire" href="{{ route('admin.audit.index') }}" aria-label="Effacer les filtres">×</a></div>
        <div class="md:col-span-6"><label for="q">Rechercher dans le texte</label><input id="q" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Exemple : BES-2026-0003, nom d'un agent, e-mail…"></div>
    </form>
    <x-carte sans-marge>
        <div class="overflow-x-auto"><table>
            <thead><tr><th class="w-40">Date</th><th>Qui</th><th>Ce qui s'est passé</th><th class="no-print"></th></tr></thead>
            <tbody>@forelse ($logs as $l)
                @php $lien = JournalLisible::lien($l); @endphp
                <tr>
                    <td class="whitespace-nowrap text-sm">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-sm">{{ $l->user?->name ?? 'Système' }}</td>
                    <td class="text-sm"><span class="font-semibold">{{ JournalLisible::libelle($l) }}</span>@if ($l->description) — {{ $l->description }}@endif
                        @if ($lien)<a class="ml-1 whitespace-nowrap" href="{{ $lien }}">Voir</a>@endif</td>
                    <td class="text-xs no-print">
                        <details><summary class="cursor-pointer text-encre-3">Détails</summary>
                            <dl class="mt-1 space-y-0.5 text-encre-2">
                                <div>Code : <span class="font-mono">{{ $l->action }}</span></div>
                                @if ($l->objet_type)<div>Élément : {{ $l->objet_type }} n° {{ $l->objet_id }}</div>@endif
                                @if ($l->user)<div>Compte : {{ $l->user->identifiant }}</div>@endif
                                @if ($l->ip)<div>Adresse IP : {{ $l->ip }}</div>@endif
                                @if ($l->contexte)<div class="font-mono break-all max-w-xs">{{ json_encode($l->contexte, JSON_UNESCAPED_UNICODE) }}</div>@endif
                            </dl>
                        </details>
                    </td>
                </tr>
            @empty <tr><td colspan="4"><x-vide message="Aucun événement pour ces critères." /></td></tr> @endforelse</tbody>
        </table></div>
        {{ $logs->links() }}
    </x-carte>
</x-layout>
