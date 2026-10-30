{{-- Boîte de dialogue demandant un motif (rejet, report, annulation, désistement…). --}}
@props(['id', 'url', 'titre', 'libelle' => 'Confirmer', 'champs' => [], 'motifRequis' => true, 'nomMotif' => 'motif', 'classeBouton' => 'btn btn-danger btn-petit', 'bouton' => null])
<button type="button" class="{{ $classeBouton }}" data-dialog-open="{{ $id }}">{{ $bouton ?? $titre }}</button>
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}_t">
    <form method="POST" action="{{ $url }}">
        @csrf
        @foreach ($champs as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <div class="flex items-center justify-between gap-4 border-b border-filet px-5 py-4">
            <h2 id="{{ $id }}_t">{{ $titre }}</h2>
            <button type="button" class="grid h-8 w-8 place-items-center rounded-md text-encre-3 hover:bg-fond" data-dialog-close aria-label="Fermer"><x-icone nom="fermer" class="h-4 w-4" /></button>
        </div>
        <div class="space-y-4 px-5 py-4 text-sm">
            {{ $slot }}
            <div>
                <label for="{{ $id }}_m">Motif ou commentaire @if ($motifRequis)<span class="text-erreur" aria-hidden="true">*</span>@else <span class="font-normal text-encre-3">(facultatif)</span>@endif</label>
                <textarea id="{{ $id }}_m" name="{{ $nomMotif }}" rows="3" @required($motifRequis) maxlength="2000"></textarea>
                @if ($motifRequis)<p class="aide">Le motif est conservé dans l'historique avec votre nom et la date.</p>@endif
            </div>
        </div>
        <div class="flex justify-end gap-2 rounded-b-[8px] border-t border-filet bg-fond-2 px-5 py-3">
            <button type="button" class="btn btn-secondaire" data-dialog-close>Annuler</button>
            <button type="submit" class="btn btn-primaire">{{ $libelle }}</button>
        </div>
    </form>
</dialog>
