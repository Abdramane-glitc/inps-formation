@php
    $existe = $besoin->exists;
@endphp
<x-layout :titre="$existe ? 'Compléter la demande' : 'Nouvelle demande'">
    <x-entete :titre="$existe ? 'Compléter la demande '.$besoin->reference : 'Demander une formation'"
              :sous-titre="$existe ? 'Corrigez ou complétez la demande comme indiqué par la RH, puis renvoyez-la.' : 'La demande est envoyée directement à la RH, qui l\'accepte, la refuse ou vous demande des précisions.'" />
    <form method="POST" action="{{ $existe ? route('besoins.update', $besoin) : route('besoins.store') }}" class="space-y-5">
        @csrf @if ($existe) @method('PUT') @endif
        <x-carte>
            <div class="space-y-4 max-w-3xl">
                @if ($services->isNotEmpty())
                    <x-champ nom="service_id" libelle="Service demandeur" type="select" :valeur="$besoin->service_id" :options="\App\Models\Service::optionsParDirection()" :requis="true" />
                @endif
                <x-champ nom="intitule" libelle="Formation souhaitée" :valeur="$besoin->intitule" :requis="true" aide="Le nom de la formation, en quelques mots. Exemple : « Excel pour le suivi des cotisations »." />
                <x-champ class="max-w-sm" nom="periode_souhaitee" libelle="Période souhaitée" :valeur="$besoin->periode_souhaitee" aide="Exemple : avant juin, 2e trimestre…" />
            </div>
        </x-carte>

        <x-carte titre="Analyse du besoin">
            <div class="space-y-4 max-w-3xl">
                <p class="text-sm text-encre-2">Une formation règle un manque de compétences. Si le problème vient d'ailleurs (organisation, matériel, effectif…), une autre action sera plus utile : ces questions aident la RH à décider.</p>
                <x-champ nom="contexte" libelle="Quel problème constatez-vous dans le service ?" type="textarea" :valeur="$besoin->contexte" :requis="true" aide="Décrivez ce qui ne va pas aujourd'hui. Exemple : « Beaucoup d'erreurs dans les tableaux de suivi des cotisations, à reprendre chaque mois »." />
                {{-- Diagnostic (2.1.0) : une formation règle un manque de savoir-faire, pas un manque de moyens ou de temps --}}
                <div class="rounded border border-filet p-3 space-y-3" data-diagnostic="#conclusion-diag">
                    <p class="font-semibold">D'où vient ce problème ? <span class="font-normal text-encre-3 text-sm">(trois questions)</span></p>
                    @foreach ([['diag_savoir', '1. Aujourd\'hui, les agents savent-ils faire correctement ce travail ?', \App\Models\BesoinFormation::DIAG_SAVOIR],
                               ['diag_moyens', '2. Ont-ils les moyens de bien le faire (matériel, logiciel, procédure claire) ?', \App\Models\BesoinFormation::DIAG_OUI_NON],
                               ['diag_temps', '3. Ont-ils le temps de bien le faire (effectif suffisant, charge raisonnable) ?', \App\Models\BesoinFormation::DIAG_OUI_NON]] as [$nom, $question, $choix])
                        <fieldset>
                            <legend class="text-sm font-semibold">{{ $question }} <span class="text-erreur" aria-hidden="true">*</span></legend>
                            <div class="flex flex-wrap gap-x-5 gap-y-1 mt-1 text-sm">
                                @foreach ($choix as $v => $lib)<label class="font-normal flex gap-2 items-center"><input type="radio" name="{{ $nom }}" value="{{ $v }}" @checked(old($nom, $besoin->$nom) === $v)> {{ $lib }}</label>@endforeach
                            </div>
                            @error($nom)<p class="erreur-champ"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@enderror
                        </fieldset>
                    @endforeach
                    <div id="conclusion-diag" class="message" hidden><x-icone nom="info" class="h-5 w-5 flex-none" /><div data-texte></div></div>
                </div>
                <fieldset>
                    <legend class="font-semibold">Quelle est la gravité du problème ? <span class="text-erreur" aria-hidden="true">*</span></legend>
                    <div class="flex flex-wrap gap-x-5 gap-y-1 mt-1 text-sm">
                        @foreach (\App\Models\BesoinFormation::GRAVITES as $v => $lib)<label class="font-normal flex gap-2 items-center"><input type="radio" name="gravite" value="{{ $v }}" @checked(old('gravite', $besoin->gravite) === $v)> {{ $lib }}</label>@endforeach
                    </div>
                    @error('gravite')<p class="erreur-champ"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@enderror
                </fieldset>
                <div>
                    <p class="font-semibold">Comment mesurer l'amélioration ?</p>
                    <div class="grid gap-3 md:grid-cols-2 mt-1">
                        <x-champ nom="situation_actuelle" libelle="Aujourd'hui" :valeur="$besoin->situation_actuelle" :requis="true" aide="Exemple : « 20 dossiers à reprendre chaque mois »." />
                        <x-champ nom="objectif_vise" libelle="Objectif après la formation" :valeur="$besoin->objectif_vise" :requis="true" aide="Exemple : « moins de 3 dossiers à reprendre par mois »." />
                    </div>
                </div>
                <fieldset>
                    <legend class="font-semibold">Qu'avez-vous déjà essayé pour régler ce problème ? <span class="text-erreur" aria-hidden="true">*</span></legend>
                    @php $solutions = collect(old('solutions_essayees', $besoin->solutions_essayees ?? [])); @endphp
                    <div class="flex flex-wrap gap-x-5 gap-y-1 mt-1 text-sm">
                        @foreach (\App\Models\BesoinFormation::SOLUTIONS as $v => $lib)<label class="font-normal flex gap-2 items-center"><input type="checkbox" name="solutions_essayees[]" value="{{ $v }}" @checked($solutions->contains($v))> {{ $lib }}</label>@endforeach
                    </div>
                    @error('solutions_essayees')<p class="erreur-champ"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@enderror
                </fieldset>
                <x-champ nom="justification_formation" libelle="Pourquoi une formation réglerait-elle ce problème ?" type="textarea" :valeur="$besoin->justification_formation" :requis="true" aide="Exemple : « Les agents n'ont jamais été formés aux formules et aux tableaux croisés d'Excel »." />
                <x-champ nom="resultat_attendu" libelle="Après la formation, que devront savoir faire les agents ?" type="textarea" :valeur="$besoin->resultat_attendu" :requis="true" aide="Ce résultat sera vérifié lors de l'évaluation, 3 à 6 mois après la formation. Exemple : « Produire seuls le tableau mensuel de suivi, sans erreur de formule »." />
            </div>
        </x-carte>

        <x-carte titre="Agents à former">
            @if ($nbAgents > 0)
                <x-choix-agents :liste="$agents" :choisis="$agentsChoisis" :url="route('agents.choisir')" id="agents" :erreur="$errors->has('agents')" />
                @error('agents')<p class="erreur-champ"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $message }}</p>@else<p class="aide">{{ $agents !== null ? 'Cochez chaque agent à former.' : 'Ajoutez chaque agent à former.' }} Le nombre de participants est calculé automatiquement.</p>@enderror
            @else
                <div class="message message-alerte"><x-icone nom="attention" class="h-5 w-5 flex-none" /><div>Aucun agent n'est enregistré pour votre service : demandez à la RH de les ajouter dans « Personnel ».</div></div>
            @endif
        </x-carte>

        <div class="flex flex-wrap gap-2">
            <button class="btn btn-primaire">{{ $existe ? 'Renvoyer la demande' : 'Envoyer la demande' }}</button>
            <a class="btn btn-secondaire" href="{{ $existe ? route('besoins.show', $besoin) : route('besoins.index') }}">Annuler</a>
        </div>
        <p class="aide">Un justificatif peut être joint ensuite, depuis la fiche de la demande.</p>
    </form>
</x-layout>
