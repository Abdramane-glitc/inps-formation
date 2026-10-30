{{-- Choix en deux temps (1.7.4) : la direction, puis un service de cette direction.
     Sans JavaScript, la liste des services reste complète (groupée par direction). --}}
@props(['nomService' => 'service_id', 'valeurService' => null, 'nomDirection' => null, 'valeurDirection' => null, 'requis' => false, 'aide' => null, 'id' => 'cs'])
@php
    $services = \App\Models\Service::actifs()->with('direction')->get()->sortBy(fn ($s) => [$s->direction?->nom, $s->nom]);
    // Directions actives, plus celles des services proposés (une direction désactivée par erreur ne doit pas cacher ses services)
    $directions = \App\Models\Direction::where('actif', true)->orWhereIn('id', $services->pluck('direction_id')->filter())->orderBy('nom')->get();
    $vService = old($nomService, $valeurService);
    $vDirection = $nomDirection ? old($nomDirection, $valeurDirection) : null;
    $vDirection ??= $services->firstWhere('id', $vService)?->direction_id;
    $erreur = $errors->first($nomService) ?: ($nomDirection ? $errors->first($nomDirection) : null);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'grid gap-3 sm:grid-cols-2']) }}>
    <div>
        <label for="{{ $id }}_dir">Direction</label>
        <select id="{{ $id }}_dir" @if ($nomDirection) name="{{ $nomDirection }}" @endif>
            <option value="">— Toutes les directions —</option>
            @foreach ($directions as $d)<option value="{{ $d->id }}" @selected((string) $vDirection === (string) $d->id)>{{ $d->code ? $d->code.' — ' : '' }}{{ $d->nom }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="{{ $id }}_srv">Service @if ($requis)<span class="text-erreur" aria-hidden="true">*</span>@endif</label>
        <select id="{{ $id }}_srv" name="{{ $nomService }}" data-services-de="#{{ $id }}_dir" @if ($erreur) aria-invalid="true" @endif>
            <option value="">— Sélectionner —</option>
            @foreach ($services->groupBy(fn ($s) => ($s->direction?->code ? $s->direction->code.' — ' : '').($s->direction?->nom ?? 'Sans direction')) as $dn => $groupe)
                <optgroup label="{{ $dn }}" data-direction="{{ $groupe->first()->direction_id }}">
                    @foreach ($groupe as $s)<option value="{{ $s->id }}" data-direction="{{ $s->direction_id }}" @selected((string) $vService === (string) $s->id)>{{ $s->nom }}</option>@endforeach
                </optgroup>
            @endforeach
        </select>
    </div>
    @if ($erreur)<p class="erreur-champ sm:col-span-2"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $erreur }}</p>@elseif ($aide)<p class="aide sm:col-span-2">{{ $aide }}</p>@endif
</div>
