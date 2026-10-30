{{-- Champ de formulaire accessible : libellé visible, valeur ancienne, aide et erreur reliées au champ.
     « icone » (2.5.2) : symbole décoratif à gauche du champ (pages de connexion). --}}
@props(['nom', 'libelle', 'type' => 'text', 'valeur' => null, 'options' => null, 'requis' => false, 'aide' => null, 'vide' => '— Sélectionner —', 'attrs' => [], 'basculer' => false, 'icone' => null])
@php
    $id = 'ch_'.str_replace(['[', ']', '.'], '_', $nom);
    $cle = str_replace(['[', ']'], ['.', ''], $nom);
    $v = old($cle, $valeur instanceof \BackedEnum ? $valeur->value : ($valeur instanceof \DateTimeInterface ? $valeur->format('Y-m-d') : $valeur));
    $enErreur = $errors->has($cle);
    $decrit = trim(($aide ? $id.'_aide ' : '').($enErreur ? $id.'_err' : ''));
@endphp
<div {{ $attributes->only('class') }}>
    @if ($type === 'checkbox')
        <input type="hidden" name="{{ $nom }}" value="0">
        <label class="inline-flex items-center gap-2.5 font-normal cursor-pointer"><input type="checkbox" id="{{ $id }}" name="{{ $nom }}" value="1" @checked($v)> {{ $libelle }}</label>
    @else
        <label for="{{ $id }}">{{ $libelle }}@if ($requis) <span class="text-erreur" aria-hidden="true">*</span>@endif</label>
        @if ($type === 'select')
            <select id="{{ $id }}" name="{{ $nom }}" @required($requis) @if ($enErreur) aria-invalid="true" @endif @if ($decrit) aria-describedby="{{ $decrit }}" @endif>
                @if ($vide !== false)<option value="">{{ $vide }}</option>@endif
                @foreach ($options as $k => $o)
                    @if (is_iterable($o)){{-- groupe d'options (ex. services regroupés par direction) --}}
                        <optgroup label="{{ $k }}">@foreach ($o as $k2 => $o2)<option value="{{ $k2 }}" @selected((string) $v === (string) $k2)>{{ $o2 }}</option>@endforeach</optgroup>
                    @else
                        <option value="{{ $k }}" @selected((string) $v === (string) $k)>{{ $o }}</option>
                    @endif
                @endforeach
            </select>
        @elseif ($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $nom }}" rows="{{ $attrs['rows'] ?? 3 }}" @required($requis) @if ($enErreur) aria-invalid="true" @endif @if ($decrit) aria-describedby="{{ $decrit }}" @endif>{{ $v }}</textarea>
        @elseif ($type === 'password' && $basculer)
            <div class="champ-mdp {{ $icone ? 'champ-icone' : '' }}">
                @if ($icone)<span class="champ-icone-symbole" aria-hidden="true"><x-icone :nom="$icone" class="h-[18px] w-[18px]" /></span>@endif
                <input type="password" id="{{ $id }}" name="{{ $nom }}" value="" @required($requis)
                       @foreach ($attrs as $a => $av) {{ $a }}="{{ $av }}" @endforeach @if ($enErreur) aria-invalid="true" @endif @if ($decrit) aria-describedby="{{ $decrit }}" @endif>
                <button type="button" data-basculer-mdp="{{ $id }}" aria-controls="{{ $id }}" aria-pressed="false" aria-label="Afficher le mot de passe">
                    <x-icone nom="oeil" class="h-[18px] w-[18px] icone-afficher" /><x-icone nom="oeil-barre" class="h-[18px] w-[18px] icone-masquer hidden" />
                </button>
            </div>
        @elseif ($icone)
            <div class="champ-icone">
                <span class="champ-icone-symbole" aria-hidden="true"><x-icone :nom="$icone" class="h-[18px] w-[18px]" /></span>
                <input type="{{ $type }}" id="{{ $id }}" name="{{ $nom }}" value="{{ $type === 'password' ? '' : $v }}" @required($requis)
                       @foreach ($attrs as $a => $av) {{ $a }}="{{ $av }}" @endforeach @if ($enErreur) aria-invalid="true" @endif @if ($decrit) aria-describedby="{{ $decrit }}" @endif>
            </div>
        @else
            <input type="{{ $type }}" id="{{ $id }}" name="{{ $nom }}" value="{{ $type === 'password' ? '' : $v }}" @required($requis)
                   @foreach ($attrs as $a => $av) {{ $a }}="{{ $av }}" @endforeach @if ($enErreur) aria-invalid="true" @endif @if ($decrit) aria-describedby="{{ $decrit }}" @endif>
        @endif
    @endif
    @if ($aide)<p class="aide" id="{{ $id }}_aide">{{ $aide }}</p>@endif
    @error($cle)<p class="erreur-champ" id="{{ $id }}_err"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@enderror
</div>
