@php use App\Enums\StatutBesoin as S; @endphp
<x-layout :titre="$b->reference">
    <x-entete :titre="$b->intitule" :sous-titre="$b->reference.' — '.$b->service->nom">
        @php [$lib, $coul] = $b->statutAffiche(); @endphp<x-badge :texte="$lib" :couleur="$coul" />
        @can('update', $b)
            <a class="btn btn-secondaire" href="{{ route('besoins.edit', $b) }}">Compléter</a>
        @endcan
        @can('delete', $b)
            <x-action-form :url="route('besoins.destroy', $b)" methode="DELETE" libelle="Supprimer la demande" classe="btn btn-danger" confirmer="Supprimer cette demande ? Elle ira dans la corbeille de la RH, qui pourra la restaurer." />
        @endcan
    </x-entete>

    @php
        $f = $b->formation;
        $fs = $f?->session;
        $etapesDemande = match ($b->statut) {
            S::Brouillon => [['Demande envoyée', 'encours'], ['Décision de la RH', 'afaire'], ['Formation programmée', 'afaire'], ['Formation terminée', 'afaire']],
            S::Soumise, S::ACompleter => [['Demande envoyée', 'fait'], ['Décision de la RH', 'encours'], ['Formation programmée', 'afaire'], ['Formation terminée', 'afaire']],
            S::Acceptee, S::AuPlan => [['Demande envoyée', 'fait'], ['Décision de la RH', 'fait'], ['Formation programmée', 'encours'], ['Formation terminée', 'afaire']],
            S::Organisee => [['Demande envoyée', 'fait'], ['Décision de la RH', 'fait'], ['Formation programmée', 'fait'], ['Formation terminée', $f?->statut === \App\Enums\StatutAction::Realisee ? 'fait' : 'encours']],
            default => null,
        };
    @endphp
    @if ($etapesDemande)<x-parcours :etapes="$etapesDemande" titre="Parcours de la demande" />@endif

    {{-- Prochaine étape, selon le statut et le profil --}}
    @if ($b->statut === S::Brouillon && auth()->user()->can('update', $b))
        <section class="prochaine"><div><h2>Prochaine étape</h2><p>Envoyer la demande à la RH</p><p class="detail">Cette demande a été enregistrée avant la mise à jour et n'a pas encore été envoyée.</p></div>
            <x-action-form :url="route('besoins.transition', $b)" libelle="Envoyer la demande" classe="btn btn-primaire" :champs="['action' => 'soumission']" confirmer="Envoyer la demande à la RH ?" /></section>
    @elseif ($b->statut === S::ACompleter && auth()->user()->can('update', $b))
        @php $precision = $b->decisions->firstWhere('action', 'demande_complement'); @endphp
        <section class="prochaine"><div><h2>Prochaine étape</h2><p>Compléter la demande, puis la renvoyer</p>
            @if ($precision?->commentaire)<p class="detail">La RH demande : « {{ $precision->commentaire }} »</p>@endif</div>
            <a class="btn btn-primaire" href="{{ route('besoins.edit', $b) }}">Compléter la demande</a></section>
    @elseif ($b->statut === S::Soumise && auth()->user()->can('instruire', $b))
        <section class="prochaine"><div><h2>Prochaine étape</h2><p>Décider de la demande</p>
            @if ($aide)<p class="detail">Consultez d'abord l'encadré « Aide à la décision » ci-dessous.</p>@endif
            <p class="detail">« Accepter et programmer » : vous fixez les dates, la formation est créée, les agents de la demande y sont inscrits et convoqués (e-mail et « Mon espace »). « Accepter et inscrire au plan » : la formation est prévue au plan annuel et sera programmée plus tard. Un motif est demandé pour les précisions et le refus.</p></div>
            <div class="flex flex-wrap gap-2">
                @permission('actions.gerer')
                <x-dialogue-motif id="accepter" :url="route('besoins.accepter', $b)" titre="Accepter et programmer la formation" bouton="Accepter et programmer" libelle="Accepter et programmer" :motif-requis="false" nom-motif="commentaire" classe-bouton="btn btn-primaire">
                    <p class="text-encre-2">La formation « {{ $b->intitule }} » sera créée et {{ $b->agents->count() ?: 'aucun' }} agent(s) de la demande y seront inscrits.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label for="accepter_d">Date de début <span class="text-erreur" aria-hidden="true">*</span></label><input id="accepter_d" type="date" name="date_debut" required></div>
                        <div><label for="accepter_f">Date de fin <span class="text-erreur" aria-hidden="true">*</span></label><input id="accepter_f" type="date" name="date_fin" required></div>
                    </div>
                    <div><label for="accepter_l">Lieu</label><input id="accepter_l" type="text" name="lieu" maxlength="255" placeholder="Exemple : salle de formation du siège"></div>
                    <div><label for="accepter_o">Organisme formateur</label><select id="accepter_o" name="organisme_id"><option value="">Aucun (formation interne)</option>@foreach ($organismes as $o)<option value="{{ $o->id }}">{{ $o->raison_sociale }}</option>@endforeach</select></div>
                    @include('formations._convoquer-aussitot')
                </x-dialogue-motif>
                <x-dialogue-motif id="plan" :url="route('besoins.plan', $b)" titre="Accepter et inscrire au plan annuel" bouton="Accepter et inscrire au plan" libelle="Inscrire au plan" :motif-requis="false" nom-motif="commentaire" classe-bouton="btn btn-secondaire">
                    <p class="text-encre-2">La demande est acceptée ; la formation est prévue au plan, et la RH la programmera plus tard (dates). Le service demandeur est informé.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label for="plan_a">Plan de l'année</label><select id="plan_a" name="annee">@foreach ([now()->year, now()->year + 1] as $an)<option @selected($an == $b->annee)>{{ $an }}</option>@endforeach</select></div>
                        <div><label for="plan_p">Période prévue <span class="text-erreur" aria-hidden="true">*</span></label><select id="plan_p" name="periode" required>@foreach (\App\Models\PlanLigne::PERIODES as $k => $v)<option value="{{ $k }}" @selected($k === 'annee')>{{ $v }}</option>@endforeach</select></div>
                    </div>
                </x-dialogue-motif>
                @endpermission
                <x-dialogue-motif id="completer" :url="route('besoins.transition', $b)" titre="Demander des précisions" bouton="Demander des précisions" libelle="Renvoyer au responsable de structure" :champs="['action' => 'demande_complement']" nom-motif="commentaire" classe-bouton="btn btn-secondaire" />
                <x-dialogue-motif id="rejeter" :url="route('besoins.transition', $b)" titre="Refuser la demande" bouton="Refuser" libelle="Refuser" :champs="['action' => 'rejet']" nom-motif="commentaire" classe-bouton="btn btn-danger"><p class="text-encre-2">Si le problème ne vient pas d'un manque de compétences, dites-le et proposez une autre action au service (organisation du travail, matériel, effectif…).</p></x-dialogue-motif>
            </div></section>
    @elseif ($b->statut === S::Soumise)
        <section class="prochaine"><div><h2>En attente</h2><p>La RH étudie la demande</p><p class="detail">Vous serez notifié de sa décision.</p></div></section>
    @elseif ($b->statut === S::AuPlan)
        @php $lp = $b->lignePlan()->with('plan')->first(); @endphp
        <section class="prochaine"><div><h2>{{ auth()->user()->aPermission('actions.gerer') ? 'Prochaine étape' : 'Demande acceptée' }}</h2>
            <p>Inscrite au plan annuel {{ $lp?->plan?->annee }} — période prévue : {{ $lp?->libellePeriode() ?? '—' }}</p>
            <p class="detail">{{ auth()->user()->aPermission('actions.gerer') ? 'Programmez la formation depuis le plan (« Programmer ») quand les dates sont connues.' : 'Les dates seront fixées par la RH ; vous serez notifié.' }}</p></div>
            @if ($lp && auth()->user()->aPermission('actions.voir'))<a class="btn {{ auth()->user()->aPermission('actions.gerer') ? 'btn-primaire' : 'btn-secondaire' }}" href="{{ route('plan.index', ['annee' => $lp->plan->annee]) }}">Voir le plan</a>@endif</section>
    @elseif ($b->statut === S::Rejetee)
        @php $refus = $b->decisions->firstWhere('action', 'rejet'); @endphp
        <div class="message message-erreur"><x-icone nom="erreur" class="h-5 w-5 flex-none" /><div>Demande refusée par la RH.@if ($refus?->commentaire) Motif : {{ $refus->commentaire }}@endif</div></div>
    @elseif ($b->statut === S::Acceptee)
        @if (auth()->user()->aPermission('actions.gerer'))
            <section class="prochaine"><div><h2>Prochaine étape</h2><p>Programmer la formation</p><p class="detail">Demande acceptée avant la mise à jour : fixez les dates pour créer la formation.</p></div>
                <x-dialogue-motif id="programmer" :url="route('besoins.accepter', $b)" titre="Programmer la formation" bouton="Programmer la formation" libelle="Programmer" :motif-requis="false" nom-motif="commentaire" classe-bouton="btn btn-primaire">
                    <p class="text-encre-2">La formation « {{ $b->intitule }} » sera créée et {{ $b->agents->count() ?: 'aucun' }} agent(s) de la demande y seront inscrits.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label for="programmer_d">Date de début <span class="text-erreur" aria-hidden="true">*</span></label><input id="programmer_d" type="date" name="date_debut" required></div>
                        <div><label for="programmer_f">Date de fin <span class="text-erreur" aria-hidden="true">*</span></label><input id="programmer_f" type="date" name="date_fin" required></div>
                    </div>
                    <div><label for="programmer_l">Lieu</label><input id="programmer_l" type="text" name="lieu" maxlength="255" placeholder="Exemple : salle de formation du siège"></div>
                    <div><label for="programmer_o">Organisme formateur</label><select id="programmer_o" name="organisme_id"><option value="">Aucun (formation interne)</option>@foreach ($organismes as $o)<option value="{{ $o->id }}">{{ $o->raison_sociale }}</option>@endforeach</select></div>
                    @include('formations._convoquer-aussitot')
                </x-dialogue-motif></section>
        @else
            <section class="prochaine"><div><h2>Demande acceptée</h2><p>La RH va organiser la formation</p><p class="detail">Les dates apparaîtront ici dès qu'elle sera programmée.</p></div></section>
        @endif
    @elseif ($b->statut === S::Organisee && $f)
        <section class="prochaine prochaine-fin"><div><h2>{{ match ($fs?->statut) { \App\Enums\StatutSession::Terminee => 'Demande satisfaite — formation terminée', \App\Enums\StatutSession::Annulee => 'Formation annulée', default => 'Demande acceptée — formation programmée' } }}</h2><p>{{ $f->reference }} — @if ($fs)du {{ date_fr($fs->date_debut) }} au {{ date_fr($fs->date_fin) }}@if ($fs->lieu), {{ $fs->lieu }}@endif @else dates à fixer @endif</p>
            <p class="detail">Statut : {{ $fs?->statut->libelle() ?? $f->statut->libelle() }}</p></div>
            @permission('actions.voir')<a class="btn btn-secondaire" href="{{ route('formations.show', $f) }}">Voir la formation</a>@endpermission</section>
    @endif

    @if ($aide)
        <x-carte titre="Aide à la décision" class="mb-5" id="aide-decision">
            <p class="text-sm text-encre-2 mb-3">Calculée par l'application à partir de l'analyse du service et des formations déjà réalisées. C'est à vous de décider.</p>
            <ul class="space-y-2">
                @foreach ($aide as $sig)
                    <li class="message {{ ['ok' => 'message-succes', 'alerte' => 'message-alerte', 'info' => 'message-info'][$sig['niveau']] }}">
                        <x-icone :nom="$sig['niveau'] === 'alerte' ? 'attention' : ($sig['niveau'] === 'ok' ? 'valide' : 'info')" class="h-5 w-5 flex-none" />
                        <div><p>{{ $sig['titre'] }}</p>@if ($sig['details'])<ul class="list-disc pl-5 mt-1 text-sm">@foreach ($sig['details'] as $d)<li>{{ $d }}</li>@endforeach</ul>@endif</div>
                    </li>
                @endforeach
            </ul>
        </x-carte>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-5">
            <x-carte titre="La demande">
                <dl class="dl-grille">
                    @if ($b->aDiagnostic())
                        <dt>Problème constaté</dt><dd class="whitespace-pre-line">{{ $b->contexte ?? '—' }}</dd>
                        <dt>Les agents savent-ils faire ?</dt><dd>{{ \App\Models\BesoinFormation::DIAG_SAVOIR[$b->diag_savoir] ?? '—' }}</dd>
                        <dt>Ont-ils les moyens ?</dt><dd>{{ \App\Models\BesoinFormation::DIAG_OUI_NON[$b->diag_moyens] ?? '—' }}</dd>
                        <dt>Ont-ils le temps ?</dt><dd>{{ \App\Models\BesoinFormation::DIAG_OUI_NON[$b->diag_temps] ?? '—' }}</dd>
                        <dt>Gravité</dt><dd>{{ \App\Models\BesoinFormation::GRAVITES[$b->gravite] ?? '—' }}</dd>
                        <dt>Mesure</dt><dd>Aujourd'hui : {{ $b->situation_actuelle ?? '—' }}<br>Objectif : {{ $b->objectif_vise ?? '—' }}</dd>
                        <dt>Déjà essayé</dt><dd>{{ implode(', ', $b->libellesSolutions()) ?: '—' }}</dd>
                        <dt>Pourquoi une formation ?</dt><dd class="whitespace-pre-line">{{ $b->justification_formation ?? '—' }}</dd>
                        <dt>Résultat attendu</dt><dd class="whitespace-pre-line">{{ $b->resultat_attendu ?? '—' }}</dd>
                    @elseif ($b->causes)
                        {{-- Demande enregistrée avec les causes à cocher (1.8.0 à 2.0.0) --}}
                        <dt>Problème constaté</dt><dd class="whitespace-pre-line">{{ $b->contexte ?? '—' }}</dd>
                        <dt>Origine du problème</dt><dd>@foreach ($b->libellesCauses() as $c)<span class="block">• {{ $c }}</span>@endforeach</dd>
                        <dt>Pourquoi une formation ?</dt><dd class="whitespace-pre-line">{{ $b->justification_formation ?? '—' }}</dd>
                        <dt>Résultat attendu</dt><dd class="whitespace-pre-line">{{ $b->resultat_attendu ?? '—' }}</dd>
                    @else
                        {{-- Demande enregistrée avant l'analyse du besoin (1.8.0) --}}
                        <dt>Pourquoi cette formation ?</dt><dd class="whitespace-pre-line">{{ $b->contexte ?? '—' }}</dd>
                    @endif
                    <dt>Agents à former</dt><dd>@if ($b->agents->isNotEmpty()){{ $b->agents->count() }} : {{ $b->agents->map(fn ($a) => $a->nom_complet)->implode(', ') }}@else{{ $b->nb_participants ? $b->nb_participants.' (agents non désignés)' : '—' }}@endif</dd>
                    <dt>Période souhaitée</dt><dd>{{ $b->periode_souhaitee ?? '—' }}</dd>
                    {{-- Champs des demandes enregistrées avant la version 1.4.0 : affichés seulement s'ils sont renseignés --}}
                    @foreach (['Domaine' => $b->domaine?->nom, 'Priorité' => $b->priorite?->libelle, 'Type souhaité' => $b->typeFormation?->libelle,
                        'Compétences visées' => $b->competences_visees, 'Objectifs' => $b->objectifs, 'Publics concernés' => $b->publics_concernes,
                        'Durée estimée' => $b->duree_estimee_jours ? $b->duree_estimee_jours.' jour(s)' : null] as $lib => $val)
                        @if ($val)<dt>{{ $lib }}</dt><dd class="whitespace-pre-line">{{ $val }}</dd>@endif
                    @endforeach
                    <dt>Demandée par</dt><dd>{{ $b->auteur->name }}, le {{ date_fr($b->soumis_le ?? $b->created_at, true) }}</dd>
                </dl>
            </x-carte>
            <x-documents :objet="$b" type="besoin" :documents="$b->documents" :categories="$categoriesDoc" :peut-deposer="auth()->user()->can('joindre', $b) || auth()->user()->can('instruire', $b)" />
        </div>
        <x-decisions :decisions="$b->decisions" />
    </div>
</x-layout>
