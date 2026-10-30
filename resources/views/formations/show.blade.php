@php
    use App\Enums\StatutSession as S;
    $u = auth()->user();
    $gerer = $u->aPermission('actions.gerer');
    $insc = $u->aPermission('inscriptions.gerer');
    $attest = $u->aPermission('attestations.emettre');
    $evalRh = $u->aPermission('evaluations.gerer');
    $annulee = $s?->statut === S::Annulee;
    $terminee = $s?->statut === S::Terminee;
    $ouverte = $s && ! $annulee && ! $terminee;
    $nb = $participants->count();
    $parService = $participants->groupBy(fn ($i) => $i->agent->service?->nom ?? 'Sans service');
    $plusieursServices = $parService->count() > 1;
    $suivis = $participants->where('resultat', 'achevee');
    $sansAttestation = $suivis->filter(fn ($i) => ! $i->attestation?->estValide())->count();

    // Trois étapes : la formation (créée), les participants (jusqu'à « Formation terminée »), les attestations.
    $etapes = [
        ['La formation', 'fait'],
        ['Participants', $terminee ? 'fait' : 'encours'],
        ['Attestations', ! $terminee ? 'afaire' : ($sansAttestation === 0 ? 'fait' : 'encours')],
    ];
@endphp
<x-layout :titre="$a->reference">
    <x-entete :titre="$a->intitule" :sous-titre="$a->reference.($s ? ' — du '.date_fr($s->date_debut).' au '.date_fr($s->date_fin).($s->lieu ? ' — '.$s->lieu : '') : ' — dates à fixer')">
        <x-badge :statut="$s?->statut ?? $a->statut" />
        @if ($s?->estATerminer())<x-badge texte="À terminer" couleur="orange" />@endif
        @if ($gerer && ($ouverte || ! $s))
            <a class="btn btn-secondaire" href="{{ route('formations.edit', $a) }}">Modifier</a>
        @endif
        @if ($s && $nb)
            <a class="btn btn-secondaire" href="{{ route('sessions.emargement', $s) }}">Liste des participants (PDF)</a>
        @endif
        @if ($gerer && app(\App\Services\SuppressionService::class)->obstacleFormation($a) === null)
            <x-action-form :url="route('suppression.unique', ['formations', $a->id])" methode="DELETE" libelle="Supprimer" classe="btn btn-danger" confirmer="Supprimer cette formation créée par erreur ? Ses participants sont retirés{{ $a->besoin ? ' et la demande '.$a->besoin->reference.' redevient « Envoyée »' : '' }}. Elle reste récupérable dans la corbeille." />
        @endif
        @if ($gerer && $ouverte)
            <x-dialogue-motif id="annuler" :url="route('sessions.annuler', $s)" titre="Annuler la formation" bouton="Annuler la formation" libelle="Annuler la formation">
                <p class="text-sm">La formation et ses inscriptions restent consultables, avec le motif de l'annulation.</p>
            </x-dialogue-motif>
        @endif
    </x-entete>

    @if ($annulee)
        <div class="message message-alerte"><x-icone nom="attention" class="h-5 w-5 flex-none" /><div>Formation annulée.@if ($s->motif_statut) Motif : {{ $s->motif_statut }}@endif</div></div>
    @else
        <x-parcours :etapes="$etapes" titre="Étapes de la formation" />

        @if ($gerer || $insc || $attest)
            @if (! $s)
                <section class="prochaine"><div><h2>Prochaine étape</h2><p>Fixer les dates de la formation</p></div>
                    @if ($gerer)<a class="btn btn-primaire" href="{{ route('formations.edit', $a) }}">Fixer les dates</a>@endif</section>
            @elseif ($nb === 0)
                <section class="prochaine"><div><h2>Prochaine étape</h2><p>Ajouter les participants</p>
                    <p class="detail">Cochez les agents dans « Ajouter des participants », plus bas.</p></div>
                    <a class="btn btn-primaire" href="#ajouter-participants">Ajouter des participants</a></section>
            @elseif (! $terminee && $insc && ! $convocation['convoquee'] && ! $s->date_debut->isPast())
                <section class="prochaine"><div><h2>Prochaine étape</h2><p>Convoquer les participants</p>
                    <p class="detail">Une convocation par participant (PDF à imprimer), la liste de ses agents à chaque responsable de structure, une notification dans « Mon espace » aux agents qui ont un accès, et la convocation par e-mail à chaque agent qui a une adresse (si la messagerie est configurée).</p></div>
                    <form method="POST" action="{{ route('formations.convoquer', $a) }}" data-confirm="Convoquer les {{ $nb }} participant(s) ?">@csrf<button class="btn btn-primaire">Convoquer les participants</button></form></section>
            @elseif (! $terminee)
                <section class="prochaine"><div><h2>Prochaine étape</h2><p>Après la formation : cocher qui l'a suivie, puis « Formation terminée »</p>
                    <p class="detail">{{ $nb }} participant(s){{ $a->besoin ? ', inscrits depuis la demande '.$a->besoin->reference : '' }}. Les participants non cochés n'auront pas d'attestation.</p></div>
                    <a class="btn btn-primaire" href="#participants">Aller aux participants</a></section>
            @elseif ($sansAttestation > 0)
                <section class="prochaine"><div><h2>Prochaine étape</h2><p>Émettre les attestations</p>
                    <p class="detail">{{ $suivis->count() }} participant(s) ont suivi la formation, {{ $sansAttestation }} n'ont pas encore d'attestation.</p></div>
                    @if ($attest)<x-action-form :url="route('attestations.session', $s)" libelle="Émettre les attestations" classe="btn btn-primaire" confirmer="Émettre les attestations des participants qui ont suivi la formation ?" />@endif</section>
            @elseif ($gerer && ! $s->rapportRedige())
                <section class="prochaine"><div><h2>Prochaine étape</h2><p>Après la formation : indiquer qui a compris, puis rédiger le rapport de réalisation</p>
                    <p class="detail">Les attestations sont émises. Indiquez pour chaque participant s'il a compris la formation, puis rédigez le rapport.</p></div>
                    <a class="btn btn-primaire" href="#apres-formation">Voir « Après la formation »</a></section>
            @else
                <section class="prochaine prochaine-fin"><div><h2>Formation terminée</h2><p>Toutes les étapes sont faites.</p>
                    <p class="detail">{{ $suivis->count() }} participant(s) sur {{ $nb }} ont suivi la formation et ont leur attestation. Mise en pratique à évaluer à partir du {{ date_fr($s->dateEvaluationFroid()) }}.</p></div></section>
            @endif
        @endif
    @endif

    @if ($nonInscrits->isNotEmpty() && ! $annulee)
        <div class="message message-alerte mb-5"><x-icone nom="attention" class="h-5 w-5 flex-none" /><div>
            <p class="font-semibold">{{ $nonInscrits->count() }} agent(s) de la demande {{ $a->besoin->reference }} ne sont pas inscrits à cette formation :</p>
            <ul class="list-disc pl-5 mt-1">@foreach ($nonInscrits as $n)<li>{{ $n['agent']->matricule }} — {{ $n['agent']->nom_complet }} : {{ $n['raison'] }}</li>@endforeach</ul>
            @if ($ouverte)<p class="mt-1 text-sm">Pour l'inscrire quand même ici, retirez-le d'abord de l'autre formation, puis ajoutez-le avec « Ajouter d'autres participants ». Sinon, changez les dates de cette formation (« Modifier »).</p>@endif
        </div></div>
    @endif

    <x-carte titre="La formation" class="mb-5">
        <dl class="dl-grille">
            @if ($a->besoin)
                <dt>Demande d'origine</dt><dd>@can('view', $a->besoin)<a href="{{ route('besoins.show', $a->besoin) }}">{{ $a->besoin->reference }}</a>@else{{ $a->besoin->reference }}@endcan — {{ $a->besoin->service?->nom }}</dd>
            @endif
            @if ($a->lignePlan && ! $a->lignePlan->trashed())
                <dt>Plan annuel</dt><dd>@permission('actions.voir')<a href="{{ route('plan.index', ['annee' => $a->lignePlan->plan->annee]) }}">Plan {{ $a->lignePlan->plan->annee }}</a>@endpermission — {{ $a->lignePlan->libellePeriode() }}</dd>
            @endif
            <dt>Dates</dt><dd>@if ($s) du {{ date_fr($s->date_debut) }} au {{ date_fr($s->date_fin) }}@if ($s->heure_debut), {{ substr($s->heure_debut, 0, 5) }} – {{ substr((string) $s->heure_fin, 0, 5) }}@endif @else à fixer @endif</dd>
            <dt>Lieu</dt><dd>{{ $s?->lieu ?? '—' }}</dd>
            <dt>Formation</dt><dd>{{ \App\Models\ActionFormation::TYPES[$a->type] ?? $a->type }}@if ($a->organisme) — {{ $a->organisme->raison_sociale }}@endif</dd>
            @if ($s && $s->formateurs->isNotEmpty())<dt>{{ $s->formateurs->count() > 1 ? 'Formateurs' : 'Formateur' }}</dt><dd>@foreach ($s->formateurs as $fm)<span class="block">{{ $fm->libelleComplet() }}</span>@endforeach</dd>@endif
            @if ($a->domaine)<dt>Domaine</dt><dd>{{ $a->domaine->nom }}</dd>@endif
            @if ($a->description)<dt>Description</dt><dd class="whitespace-pre-line">{{ $a->description }}</dd>@endif
            @if ($a->objectifs_pedagogiques)<dt>Objectifs</dt><dd class="whitespace-pre-line">{{ $a->objectifs_pedagogiques }}</dd>@endif
        </dl>
    </x-carte>

    @if ($s && $convocation && $convocation['convoquee'] && $insc && ! $annulee)
        <div class="message {{ $convocation['a_reconvoquer'] ? 'message-alerte' : 'message-info' }} mb-5 flex-wrap"><x-icone :nom="$convocation['a_reconvoquer'] ? 'attention' : 'info'" class="h-5 w-5 flex-none" />
            <div class="flex-1">
                @if ($convocation['a_reconvoquer'])
                    Les dates, les horaires ou le lieu ont changé depuis la convocation du {{ date_fr($convocation['le']) }} : envoyez une convocation rectificative.
                @else
                    Participants convoqués le {{ date_fr($convocation['le'], true) }}.@if ($convocation['non_convoques']) {{ $convocation['non_convoques'] }} participant(s) ajouté(s) depuis ne sont pas encore convoqué(s).@endif
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if (! $terminee && ($convocation['a_reconvoquer'] || $convocation['non_convoques']))
                    <form method="POST" action="{{ route('formations.convoquer', $a) }}" data-confirm="{{ $convocation['a_reconvoquer'] ? 'Envoyer une convocation rectificative à tous les participants ?' : 'Convoquer les participants ajoutés depuis la convocation ?' }}">@csrf
                        @unless ($convocation['a_reconvoquer'])<input type="hidden" name="nouveaux" value="1">@endunless
                        <button class="btn btn-primaire btn-petit">{{ $convocation['a_reconvoquer'] ? 'Envoyer la convocation rectificative' : 'Convoquer les nouveaux' }}</button></form>
                @endif
                <a class="btn btn-secondaire btn-petit" href="{{ route('formations.convocations', $a) }}">Convocations (PDF)</a>
            </div>
        </div>
    @endif

    @if ($s)
        <x-carte :titre="($limite ? 'Participants de votre service (' : 'Participants (').$nb.')'.($plusieursServices ? ' — '.$parService->count().' services' : '')" sans-marge class="mb-5" id="participants">
            @if ($nb === 0)
                <x-vide message="Aucun participant pour l'instant." />
            @else
                <form method="POST" action="{{ route('formations.terminer', $a) }}" id="form-suivi">@csrf</form>
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Agent</th><th>{{ $plusieursServices ? 'Fonction' : 'Service' }}</th><th>{{ $terminee ? 'A suivi la formation' : 'A suivi ?' }}</th><th>Attestation</th>@if ($insc && $ouverte)<th class="no-print"></th>@endif</tr></thead>
                    <tbody>@foreach ($parService as $nomService => $groupe)
                        @if ($plusieursServices)
                            <tr class="bg-fond"><th colspan="5" class="text-left text-sm font-semibold">{{ $nomService }} <span class="font-normal text-encre-3">({{ $groupe->count() }})</span></th></tr>
                        @endif
                        @foreach ($groupe as $i)
                        <tr>
                            <td>{{ $i->agent->nom_complet }}<div class="text-xs text-encre-3">{{ $i->agent->matricule }}</div></td>
                            <td>{{ $plusieursServices ? ($i->agent->fonction ?? '—') : $i->agent->service?->nom }}</td>
                            <td>
                                @if ($gerer && ! $annulee)
                                    <label class="font-normal inline-flex items-center gap-2"><input type="checkbox" form="form-suivi" name="suivi[]" value="{{ $i->id }}" @checked($i->resultat ? $i->resultat === 'achevee' : true)> Oui</label>
                                @else
                                    {{ $i->resultat === 'achevee' ? 'Oui' : ($i->resultat === 'non_achevee' ? 'Non' : '—') }}
                                @endif
                            </td>
                            <td>
                                @if ($i->attestation?->estValide())
                                    @if ($attest)<a href="{{ route('attestations.pdf', $i->attestation) }}">{{ $i->attestation->reference }}</a>
                                        @php $imp = $impressions[$i->attestation->id] ?? null; @endphp
                                        <div class="text-xs text-encre-3">{{ $imp ? 'Imprimée '.$imp['nombre'].' fois — dernière le '.date_fr($imp['derniere']) : 'Pas encore imprimée' }}</div>
                                    @else{{ $i->attestation->reference }}@endif
                                @elseif ($attest && $terminee && $i->resultat === 'achevee')
                                    <x-action-form :url="route('attestations.store', $i)" libelle="Émettre" />
                                @else — @endif
                            </td>
                            @if ($insc && $ouverte)
                                <td class="text-right no-print"><x-action-form :url="route('formations.participants.retirer', $i)" libelle="Retirer" classe="btn btn-discret btn-petit" :confirmer="'Retirer '.$i->agent->nom_complet.' de la formation ?'" /></td>
                            @endif
                        </tr>
                        @endforeach
                    @endforeach</tbody>
                </table></div>
                @if ($gerer && ! $annulee)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-filet px-4 py-3 no-print">
                        <p class="aide m-0">{{ $terminee ? 'Pour corriger, modifiez les cases puis enregistrez. Une attestation émise doit d\'abord être annulée.' : 'Décochez les participants absents. À faire une fois la formation passée.' }}</p>
                        <button form="form-suivi" class="btn {{ $terminee ? 'btn-secondaire' : 'btn-primaire' }}" data-confirm="{{ $terminee ? 'Enregistrer la correction du suivi ?' : 'Marquer la formation comme terminée ? Seuls les participants cochés pourront recevoir une attestation.' }}">{{ $terminee ? 'Enregistrer la correction' : 'Formation terminée' }}</button>
                    </div>
                @endif
            @endif
        </x-carte>

        @if ($insc && $ouverte)
            {{-- Les agents de la demande sont déjà inscrits : l'ajout n'est qu'une option, repliée par défaut --}}
            <details class="carte mb-5 no-print" id="ajouter-participants" @if ($nb === 0) open @endif>
                <summary class="cursor-pointer px-4 py-3 font-semibold text-sm">{{ $nb === 0 ? 'Ajouter des participants' : 'Ajouter d\'autres participants (facultatif)' }}</summary>
                <form method="POST" action="{{ route('formations.participants', $a) }}" class="space-y-3 px-4 pb-4">
                    @csrf
                    @if ($agentsDisponibles !== null && $agentsDisponibles->isEmpty())
                        <p class="text-encre-3 text-sm">Tous les agents actifs sont déjà inscrits.</p>
                    @else
                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="md:col-span-2"><x-choix-agents :liste="$agentsDisponibles" :url="route('agents.choisir', ['session' => $s->id])" id="agents-dispo" placeholder="Matricule, nom ou prénom…" /></div>
                            <div><label for="srv-coll">Ou ajouter tout un service</label>
                                <select id="srv-coll" name="service_id"><option value="">—</option>@foreach ($servicesAjout as $sv)<option value="{{ $sv->id }}">{{ $sv->direction?->sigle ?: $sv->direction?->nom }} — {{ $sv->nom }}</option>@endforeach</select></div>
                        </div>
                    @endif
                    <button class="btn btn-primaire">Ajouter</button>
                </form>
            </details>
        @endif
    @endif

    @if ($apres)
        @php $externes = $s->formateurs->filter(fn ($f) => $f->estExterne()); @endphp
        <x-carte titre="Après la formation" class="mb-5" id="apres-formation">
            <div class="divide-y divide-filet">
                @if ($evalRh)
                    <div class="py-3 first:pt-0 flex flex-wrap items-center justify-between gap-3">
                        <div><p class="font-semibold">Ont-ils compris ? (en fin de formation)</p>
                            <p class="text-sm text-encre-2">@if ($apres['chaud']['faites']){{ $apres['chaud']['faites'] }} sur {{ $apres['chaud']['attendues'] }} participant(s) : {{ collect(\App\Models\EvaluationAcquis::REPONSES)->map(fn ($l, $k) => $l.' '.$apres['chaud']['reponses'][$k])->implode(', ') }}@if ($apres['chaud']['note_moyenne'] !== null) — note moyenne {{ number_format($apres['chaud']['note_moyenne'], 1, ',', '') }}/{{ \App\Models\EvaluationAcquis::NOTE_MAX }}@endif @else La RH indique pour chaque participant s'il a compris la formation. @endif</p></div>
                        <div class="flex flex-wrap gap-2"><a class="btn {{ $apres['chaud']['faites'] ? 'btn-secondaire' : 'btn-primaire' }} btn-petit" href="{{ route('evaluations.chaud', $a) }}">{{ $apres['chaud']['faites'] ? 'Modifier' : 'Indiquer qui a compris' }}</a></div>
                    </div>
                @endif
                @if ($gerer)
                    <div class="py-3 flex flex-wrap items-center justify-between gap-3">
                        <div><p class="font-semibold">Rapport de réalisation</p>
                            <p class="text-sm text-encre-2">@if ($s->rapportRedige())Rédigé le {{ date_fr($s->rapport_redige_le) }}@if ($s->redacteurRapport) par {{ $s->redacteurRapport->name }}@endif. @else À rédiger : déroulement, difficultés, recommandations ; le reste est rempli automatiquement. @endif</p></div>
                        <div class="flex flex-wrap gap-2">@if ($s->rapportRedige())<a class="btn btn-secondaire btn-petit" href="{{ route('rapport-realisation.pdf', $a) }}">Télécharger (PDF)</a>@endif<a class="btn {{ $s->rapportRedige() ? 'btn-secondaire' : 'btn-primaire' }} btn-petit" href="{{ route('rapport-realisation.edit', $a) }}">{{ $s->rapportRedige() ? 'Modifier le rapport' : 'Rédiger le rapport' }}</a></div>
                    </div>
                @endif
                @if ($attest && $externes->isNotEmpty())
                    <div class="py-3">
                        <p class="font-semibold">Attestations d'intervention des formateurs externes</p>
                        <p class="text-sm text-encre-2 mb-2">Modèle proposé, à valider par l'INPS. Une attestation par formateur ; la retélécharger redonne la même.</p>
                        <ul class="space-y-2 text-sm">@foreach ($externes as $fm)
                            @php $ati = $apres['attestationsIntervenants']->get($fm->id); @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2"><span>{{ $fm->libelleComplet() }}@if ($ati) — <span class="font-medium tabular-nums">{{ $ati->reference }}</span>, émise le {{ date_fr($ati->emise_le) }}@endif</span>
                                @if ($ati)<a class="btn btn-secondaire btn-petit" href="{{ route('attestations-intervenants.pdf', $ati) }}">Télécharger (PDF)</a>
                                @else<x-action-form :url="route('attestations-intervenants.emettre', [$a, $fm])" libelle="Émettre l'attestation" classe="btn btn-primaire btn-petit" confirmer="Émettre l'attestation d'intervention de {{ $fm->nom_complet }} ?" />@endif</li>
                        @endforeach</ul>
                    </div>
                @endif
                <div class="py-3 last:pb-0 flex flex-wrap items-center justify-between gap-3">
                    <div><p class="font-semibold">Appliquent-ils ce qu'ils ont appris ? ({{ $s->delaiFroid() }} mois après)</p>
                        <p class="text-sm text-encre-2">Le responsable de structure évalue chaque agent formé à partir du <strong>{{ date_fr($s->dateEvaluationFroid()) }}</strong>.@if (! $limite) {{ $apres['froid']['faites'] }} évaluation(s) faite(s) sur {{ $apres['froid']['attendues'] }}@if ($apres['froid']['faites']) : {{ collect(\App\Models\EvaluationAcquis::REPONSES)->map(fn ($l, $k) => $l.' '.$apres['froid']['reponses'][$k])->implode(', ') }}@endif.@endif</p></div>
                    <div class="flex flex-wrap gap-2 items-end">
                        @if ($gerer)
                            <form method="POST" action="{{ route('evaluations.delai', $a) }}" class="flex gap-2 items-end">@csrf
                                <div><label for="delai_froid" class="text-xs">Délai</label><select id="delai_froid" name="mois">@foreach (\App\Models\SessionFormation::DELAIS_FROID as $m)<option value="{{ $m }}" @selected($s->delaiFroid() === $m)>{{ $m }} mois</option>@endforeach</select></div>
                                <button class="btn btn-secondaire btn-petit">Changer</button></form>
                        @endif
                        @if ($u->aUneDesPermissions('evaluations.froid', 'evaluations.gerer'))<a class="btn btn-secondaire btn-petit" href="{{ route('evaluations.index') }}">Évaluations</a>@endif
                    </div>
                </div>
            </div>
        </x-carte>
    @endif

    <x-documents :objet="$a" type="action" :documents="$a->documents" :categories="$categoriesDoc" :peut-deposer="$gerer" />
    @if ($s && $s->documents->isNotEmpty())
        {{-- Documents déposés sur la session avant la version 1.3.0 --}}
        <div class="mt-5"><x-documents :objet="$s" type="session" :documents="$s->documents" :categories="$categoriesDoc" :peut-deposer="false" /></div>
    @endif
</x-layout>
