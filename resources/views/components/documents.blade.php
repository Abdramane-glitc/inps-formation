{{-- Pièces jointes d'un objet : liste + dépôt (si autorisé). Téléchargement via contrôleur autorisé. --}}
@props(['objet', 'type', 'documents', 'categories', 'peutDeposer' => false])
@php $u = auth()->user(); @endphp
<x-carte titre="Documents">
    @php $visibles = $documents->filter(fn ($d) => $u->can('view', $d)); @endphp
    @if ($visibles->isEmpty())
        <p class="text-sm text-slate-500">Aucun document accessible.</p>
    @else
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach ($visibles as $d)
                <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <a href="{{ route('documents.telecharger', $d) }}">{{ $d->titre }}</a>
                        <span class="text-slate-500">— {{ $d->categorie?->libelle ?? 'Sans catégorie' }}, {{ $d->tailleLisible() }}, {{ date_fr($d->created_at) }}</span>
                        @if ($d->confidentiel) <x-badge texte="Confidentiel" couleur="rouge" /> @endif
                    </span>
                    @can('delete', $d)
                        <x-action-form :url="route('documents.destroy', $d)" methode="DELETE" libelle="Retirer" classe="btn btn-danger btn-petit" confirmer="Retirer ce document ?" />
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
    @if ($peutDeposer)
        <form method="POST" action="{{ route('documents.store', [$type, $objet->id]) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-4 items-end border-t border-slate-100 pt-4 no-print">
            @csrf
            <div class="md:col-span-2"><label for="doc_f_{{ $type }}">Fichier (PDF, image, Word, Excel, CSV — {{ \App\Services\DocumentService::tailleMaxKo() }} Ko max.)</label><input id="doc_f_{{ $type }}" type="file" name="fichier" required></div>
            <div><label for="doc_t_{{ $type }}">Titre</label><input id="doc_t_{{ $type }}" type="text" name="titre" maxlength="255"></div>
            <div><label for="doc_c_{{ $type }}">Catégorie</label>
                <select id="doc_c_{{ $type }}" name="categorie_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select></div>
            @if ($u->aPermission('documents.confidentiels'))
                <label class="inline-flex items-center gap-2 font-normal"><input type="checkbox" name="confidentiel" value="1"> Confidentiel</label>
            @endif
            <div><button class="btn btn-primaire">Déposer</button></div>
        </form>
    @endif
</x-carte>
