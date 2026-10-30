{{-- Bouton déclenchant une action POST protégée CSRF, avec confirmation éventuelle. --}}
@props(['url', 'libelle', 'methode' => 'POST', 'confirmer' => null, 'classe' => 'btn btn-secondaire btn-petit', 'champs' => []])
<form method="POST" action="{{ $url }}" class="inline" @if ($confirmer) data-confirm="{{ $confirmer }}" @endif>
    @csrf
    @if (strtoupper($methode) !== 'POST') @method($methode) @endif
    @foreach ($champs as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <button type="submit" class="{{ $classe }}">{{ $libelle }}</button>
</form>
