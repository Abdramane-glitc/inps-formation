{{-- Boîte de dialogue contenant un formulaire libre (champs fournis dans le slot). --}}
@props(['id', 'url', 'titre', 'libelle' => 'Enregistrer', 'methode' => 'POST', 'classeBouton' => 'btn btn-secondaire btn-petit', 'bouton' => null])
<button type="button" class="{{ $classeBouton }}" data-dialog-open="{{ $id }}">{{ $bouton ?? $titre }}</button>
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}_t">
    <form method="POST" action="{{ $url }}">
        @csrf
        @if (strtoupper($methode) !== 'POST') @method($methode) @endif
        <div class="flex items-center justify-between gap-4 border-b border-filet px-5 py-4">
            <h2 id="{{ $id }}_t">{{ $titre }}</h2>
            <button type="button" class="grid h-8 w-8 place-items-center rounded-md text-encre-3 hover:bg-fond" data-dialog-close aria-label="Fermer"><x-icone nom="fermer" class="h-4 w-4" /></button>
        </div>
        <div class="space-y-4 px-5 py-4 text-sm text-left">{{ $slot }}</div>
        <div class="flex justify-end gap-2 rounded-b-[8px] border-t border-filet bg-fond-2 px-5 py-3">
            <button type="button" class="btn btn-secondaire" data-dialog-close>Annuler</button>
            <button type="submit" class="btn btn-primaire">{{ $libelle }}</button>
        </div>
    </form>
</dialog>
