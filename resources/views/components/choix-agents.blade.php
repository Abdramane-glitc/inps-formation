{{--
    Choix des agents d'un formulaire (29/10/2026), deux modes selon le nombre d'agents visibles :
     - « liste » (jusqu'à BesoinController::LISTE_MAX agents, soit tout le personnel de l'INPS) : toutes les cases à cocher,
       rangées par direction puis service, filtrées pendant la frappe ;
     - « recherche » (au-delà, en réserve) : recherche dans TOUT le personnel côté serveur
       (route agents.choisir, mêmes droits que la page Personnel), puis ajout un par un ; les agents choisis restent affichés.
    Le comportement est dans resources/js/app.js (attributs data-*) : aucun script dans la page (CSP).
--}}
@props(['liste' => null, 'choisis' => collect(), 'url' => null, 'id' => 'agents', 'erreur' => false, 'placeholder' => 'Matricule, nom ou prénom…'])
@php $idsChoisis = $choisis->pluck('id')->map(fn ($v) => (int) $v); @endphp

@if ($liste !== null)
    <input id="{{ $id }}-filtre" type="search" placeholder="{{ $placeholder }}" data-filtre-liste="#{{ $id }}-liste" class="mb-2" aria-label="Rechercher un agent" autocomplete="off">
    @php
        // Groupes : direction, puis service ; un agent sans service est rangé sous sa direction
        $cle = function ($a) {
            $d = $a->direction ?? $a->service?->direction;
            $dir = $d ? ($d->sigle && $d->sigle !== $d->nom ? $d->sigle.' — '.$d->nom : $d->nom) : 'Sans direction';

            return $dir.($a->service ? ' › '.$a->service->nom : '');
        };
        $groupes = $liste->sortBy(fn ($a) => $cle($a).' '.$a->nom.' '.$a->prenom)->groupBy($cle);
    @endphp
    <p class="aide mb-1">{{ $liste->count() }} agent(s). Tapez un nom, un prénom, un matricule ou un sigle de direction (ex. « DRH ») pour réduire la liste.</p>
    <div id="{{ $id }}-liste" class="max-h-96 overflow-y-auto text-sm border rounded p-2 space-y-3 {{ $erreur ? 'border-erreur' : 'border-filet' }}">
        @foreach ($groupes as $nomService => $groupe)
            <fieldset>
                @if ($groupes->count() > 1)<legend class="font-semibold text-encre-2 mb-1 sticky top-0 bg-white w-full py-0.5">{{ $nomService }} <span class="font-normal text-encre-3">({{ $groupe->count() }})</span></legend>@endif
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-1">
                    @foreach ($groupe as $a)
                        <label class="font-normal flex gap-2 items-center" data-filtre-texte="{{ $a->matricule }} {{ $a->nom }} {{ $a->prenom }} {{ $nomService }} {{ $a->direction?->nom }}"><input type="checkbox" name="agents[]" value="{{ $a->id }}" @checked($idsChoisis->contains($a->id))> {{ $a->matricule }} — {{ $a->nom_complet }}</label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        <p class="text-encre-3 hidden" data-filtre-vide>Aucun agent ne correspond à cette recherche.</p>
    </div>
    <p class="aide mt-1"><strong data-compte-coches="#{{ $id }}-liste">{{ $liste->whereIn('id', $idsChoisis)->count() }}</strong> agent(s) coché(s).</p>
@else
    <div class="choix-agents" data-choix-agents="{{ $url }}">
        <label for="{{ $id }}-recherche" class="sr-only">Rechercher un agent</label>
        <input id="{{ $id }}-recherche" type="search" placeholder="{{ $placeholder }}" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="{{ $id }}-resultats" aria-autocomplete="list" data-choix-recherche>
        <ul id="{{ $id }}-resultats" class="choix-agents-resultats" role="listbox" aria-label="Agents trouvés" hidden data-choix-resultats></ul>
        <p class="aide" data-choix-etat>Tapez au moins 2 lettres du nom, du prénom ou du matricule, puis cliquez sur l'agent (ou Entrée) pour l'ajouter.</p>
        <div class="choix-agents-selection {{ $erreur ? 'border-erreur' : 'border-filet' }}" data-choix-selection aria-live="polite">
            @foreach ($choisis as $a)
                <span class="choix-agents-puce" data-id="{{ $a->id }}">{{ $a->matricule }} — {{ $a->nom_complet }}<input type="hidden" name="agents[]" value="{{ $a->id }}"><button type="button" data-choix-retirer aria-label="Retirer {{ $a->nom_complet }}">×</button></span>
            @endforeach
            <span class="text-encre-3 text-sm" data-choix-aucun @if ($choisis->isNotEmpty()) hidden @endif>Aucun agent choisi.</span>
        </div>
        <p class="aide"><span data-choix-compte>{{ $choisis->count() }}</span> agent(s) choisi(s).</p>
    </div>
@endif
