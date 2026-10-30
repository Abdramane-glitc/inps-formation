@php
    use App\Models\PlanLigne;
    $trimestres = ['T1' => '1er trimestre', 'T2' => '2e trimestre', 'T3' => '3e trimestre', 'T4' => '4e trimestre', 'annee' => 'Dans l\'année (période à préciser)'];
    $total = $lignes->count();
    $faites = ($etats['programmee'] ?? 0) + ($etats['terminee'] ?? 0);
    $couleurs = ['prevue' => 'gris', 'programmee' => 'bleu', 'terminee' => 'vert', 'annulee' => 'rouge'];
@endphp
<x-layout :titre="'Plan annuel '.$annee">
    <x-entete :titre="'Plan annuel de formation '.$annee"
              :sous-titre="$gerer ? 'Les formations prévues pour l\'année. « Programmer » fixe les dates et crée la formation.' : 'Les formations prévues pour votre service.'">
        @if ($plan?->estValide())<x-badge texte="Validé" couleur="vert" />@elseif ($plan)<x-badge texte="En préparation" couleur="orange" />@endif
        @if ($total)
            <a class="btn btn-secondaire" href="{{ route('plan.export', [$annee, 'pdf']) }}">PDF</a>
            <a class="btn btn-secondaire" href="{{ route('plan.export', [$annee, 'xlsx']) }}">Excel</a>
        @endif
        @if ($gerer && $total && ! $plan->estValide())
            <x-dialogue-motif id="valider-plan" :url="route('plan.valider', $annee)" titre="Valider le plan" bouton="Valider le plan" libelle="Valider" :motif-requis="false" nom-motif="commentaire" classe-bouton="btn btn-primaire">
                <p>Le plan {{ $annee }} ({{ $total }} formation(s)) sera marqué validé, avec votre nom et la date. Il pourra encore être complété.</p>
            </x-dialogue-motif>
        @endif
    </x-entete>

    <form method="GET" class="flex flex-wrap items-end gap-2 mb-4 no-print">
        <div><label for="an">Année</label><select id="an" name="annee" data-soumission-auto>@foreach ($annees as $a)<option @selected($a == $annee)>{{ $a }}</option>@endforeach</select></div>
        <button class="btn btn-secondaire">Afficher</button>
    </form>

    @if ($plan?->estValide())
        <div class="message message-succes mb-4"><x-icone nom="succes" class="h-5 w-5 flex-none" /><div>Plan validé le {{ date_fr($plan->valide_le) }} par {{ $plan->validePar?->name ?? '—' }}.@if ($plan->commentaire_validation) {{ $plan->commentaire_validation }}@endif</div></div>
    @endif

    <div class="grid gap-3 sm:grid-cols-4 mb-5">
        <x-kpi libelle="Formations prévues" :valeur="$total" />
        <x-kpi libelle="Programmées ou terminées" :valeur="$faites" :detail="$total ? round(100 * $faites / $total).' % du plan' : null" />
        <x-kpi libelle="Encore à programmer" :valeur="$etats['prevue'] ?? 0" />
        <x-kpi libelle="Agents prévus" :valeur="$lignes->sum('nb_agents')" />
    </div>

    @if ($gerer)
        <details class="carte mb-5 no-print" @if (! $total || $errors->any()) open @endif>
            <summary class="cursor-pointer px-4 py-3 font-semibold text-sm">Ajouter une formation au plan {{ $annee }}</summary>
            <form method="POST" action="{{ route('plan.lignes.store') }}" class="px-4 pb-4 space-y-3 max-w-2xl">
                @csrf <input type="hidden" name="annee" value="{{ $annee }}">
                @include('plan._champs', ['l' => null, 'p' => 'nouv'])
                <button class="btn btn-primaire">Ajouter au plan</button>
                <p class="aide">Une demande d'un service s'ajoute au plan depuis sa fiche : « Accepter et inscrire au plan ».</p>
            </form>
        </details>
    @endif

    @if (! $total)
        <x-carte><x-vide :message="'Aucune formation prévue au plan '.$annee.($gerer ? '' : ' pour votre service').'.'" /></x-carte>
    @endif

    @foreach ($trimestres as $t => $titreT)
        @continue(! isset($parTrimestre[$t]))
        <x-carte :titre="$titreT.' ('.$parTrimestre[$t]->count().')'" sans-marge class="mb-5">
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Période</th><th>Formation prévue</th><th>Service</th><th class="text-right">Agents</th><th>État</th>@if ($gerer)<th class="no-print"></th>@endif</tr></thead>
                <tbody>@foreach ($parTrimestre[$t] as $l)
                    @php $etat = $l->etat(); $f = $l->formation; @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $l->libellePeriode() }}</td>
                        <td>{{ $l->intitule }}
                            <div class="text-xs text-encre-3">
                                {{ $l->type ? ucfirst($l->type) : '' }}@if ($l->organisme){{ $l->type ? ' — ' : '' }}{{ $l->organisme->raison_sociale }}@endif
                                @if ($l->besoin) Demande @can('view', $l->besoin)<a href="{{ route('besoins.show', $l->besoin) }}">{{ $l->besoin->reference }}</a>@else{{ $l->besoin->reference }}@endcan @endif
                                @if ($plan->estValide() && $l->created_at->gt($plan->valide_le)) <x-badge texte="Ajoutée après validation" couleur="orange" />@endif
                            </div></td>
                        <td>{{ $l->service?->nom ?? 'Plusieurs services' }}</td>
                        <td class="text-right tabular-nums">{{ $l->nb_agents ?? '—' }}</td>
                        <td><x-badge :texte="PlanLigne::ETATS[$etat]" :couleur="$couleurs[$etat]" />
                            @if ($f && $f->estVisiblePar(auth()->user()))<div class="text-xs mt-1"><a href="{{ route('formations.show', $f) }}">{{ $f->reference }}</a>@if ($f->session) — {{ date_fr($f->session->date_debut) }}@endif</div>@endif</td>
                        @if ($gerer)
                            <td class="text-right whitespace-nowrap no-print">
                                @if (in_array($etat, ['prevue', 'annulee'], true))
                                    <x-dialogue-formulaire :id="'prog-'.$l->id" :url="route('plan.lignes.programmer', $l)" :titre="'Programmer « '.$l->intitule.' »'" bouton="Programmer" libelle="Programmer" classe-bouton="btn btn-primaire btn-petit">
                                        <p class="text-encre-2">La formation sera créée{{ $l->besoin && $l->besoin->statut === \App\Enums\StatutBesoin::AuPlan ? ' et les agents de la demande '.$l->besoin->reference.' y seront inscrits' : ' ; vous ajouterez ensuite les participants' }}.</p>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div><label for="prog-{{ $l->id }}_d">Date de début <span class="text-erreur" aria-hidden="true">*</span></label><input id="prog-{{ $l->id }}_d" type="date" name="date_debut" required></div>
                                            <div><label for="prog-{{ $l->id }}_f">Date de fin <span class="text-erreur" aria-hidden="true">*</span></label><input id="prog-{{ $l->id }}_f" type="date" name="date_fin" required></div>
                                        </div>
                                        <div><label for="prog-{{ $l->id }}_l">Lieu</label><input id="prog-{{ $l->id }}_l" type="text" name="lieu" maxlength="255"></div>
                                        <div><label for="prog-{{ $l->id }}_o">Organisme formateur</label><select id="prog-{{ $l->id }}_o" name="organisme_id"><option value="">Aucun (formation interne)</option>@foreach ($organismes as $id => $nom)<option value="{{ $id }}" @selected($l->organisme_id == $id)>{{ $nom }}</option>@endforeach</select></div>
                                        @if ($l->besoin && $l->besoin->statut === \App\Enums\StatutBesoin::AuPlan)@include('formations._convoquer-aussitot')@endif
                                    </x-dialogue-formulaire>
                                @endif
                                @if ($etat === 'prevue')
                                    <x-dialogue-formulaire :id="'mod-'.$l->id" :url="route('plan.lignes.update', $l)" methode="PUT" titre="Modifier la ligne du plan" bouton="Modifier">
                                        @include('plan._champs', ['l' => $l, 'p' => 'mod'.$l->id])
                                    </x-dialogue-formulaire>
                                    <x-dialogue-motif :id="'ret-'.$l->id" :url="route('plan.lignes.destroy', $l)" :champs="['_method' => 'DELETE']" titre="Retirer du plan" bouton="Retirer" libelle="Retirer" :motif-requis="(bool) $l->besoin_id" classe-bouton="btn btn-discret btn-petit">
                                        <p>« {{ $l->intitule }} » sera retirée du plan.@if ($l->besoin_id) La demande {{ $l->besoin->reference }} redeviendra « Envoyée » et son auteur verra le motif.@endif</p>
                                    </x-dialogue-motif>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach</tbody>
            </table></div>
        </x-carte>
    @endforeach
</x-layout>
