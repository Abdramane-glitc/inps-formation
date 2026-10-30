@props(['resumeErreurs' => true])
@php
    $blocs = [];
    if (session('succes')) { $blocs[] = ['succes', 'succes', session('succes')]; }
    if (session('erreur')) { $blocs[] = ['erreur', 'erreur', session('erreur')]; }
    if (session('avertissement')) { $blocs[] = ['alerte', 'attention', session('avertissement')]; }
@endphp
@foreach ($blocs as [$type, $icone, $texte])
    <div class="message message-{{ $type }}" role="{{ $type === 'succes' ? 'status' : 'alert' }}">
        <x-icone :nom="$icone" class="h-5 w-5 mt-px" /><p>{{ $texte }}</p>
    </div>
@endforeach
@if (session('avertissements'))
    <div class="message message-alerte" role="alert">
        <x-icone nom="attention" class="h-5 w-5 mt-px" />
        <div><p class="font-semibold mb-1">Points d'attention</p>
            <ul class="list-disc pl-5 space-y-0.5">@foreach (session('avertissements') as $a)<li>{{ $a }}</li>@endforeach</ul></div>
    </div>
@endif
@if (session('mot_de_passe_temporaire'))
    <div class="message message-info" role="alert">
        <x-icone nom="cle" class="h-5 w-5 mt-px" />
        <div>
            <p>Mot de passe temporaire, affiché une seule fois : <code class="ml-1 select-all rounded border border-inps-100 bg-white px-2 py-0.5 font-mono text-base text-encre">{{ session('mot_de_passe_temporaire') }}</code></p>
            <p class="mt-1 text-xs">L'utilisateur devra le changer à sa première connexion. Transmettez-le par un canal sûr.</p>
        </div>
    </div>
@endif
@if ($resumeErreurs && $errors->any())
    <div class="message message-erreur" role="alert">
        <x-icone nom="erreur" class="h-5 w-5 mt-px" />
        <div><p class="font-semibold mb-1">Le formulaire n'a pas été enregistré. Corrigez les points suivants :</p>
            <ul class="list-disc pl-5 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    </div>
@endif
