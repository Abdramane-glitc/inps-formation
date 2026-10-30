{{-- Barre « Supprimer la sélection » pour une liste à cases à cocher (1.6.0).
     Les cases de la liste portent form="selection-{type}" name="ids[]" class="sel-{type}". --}}
@props(['type', 'libelle' => 'élément(s)'])
<form method="POST" action="{{ route('suppression.selection', $type) }}" id="selection-{{ $type }}"
      class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-filet bg-fond-2 no-print"
      data-confirm="Supprimer les {{ $libelle }} cochés ? Ceux qui ne peuvent pas l'être seront signalés avec la raison.">
    @csrf
    <label class="font-normal inline-flex items-center gap-2 text-sm"><input type="checkbox" data-tout-cocher=".sel-{{ $type }}"> Tout cocher</label>
    <button type="submit" class="btn btn-secondaire btn-petit text-erreur">Supprimer la sélection</button>
    <span class="text-xs text-encre-3">Seules les erreurs de saisie peuvent être supprimées ; sinon, l'application indique quoi faire.</span>
</form>
