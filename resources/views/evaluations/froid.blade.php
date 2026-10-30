@php
    use App\Models\EvaluationAcquis as E;
    $b = $a->besoin;
    $partir = $s->dateEvaluationFroid();
    $possible = ! today()->lt($partir);
@endphp
<x-layout titre="Appliquent-ils ce qu'ils ont appris ?">
    <x-entete titre="Appliquent-ils ce qu'ils ont appris ?" :sous-titre="$a->intitule.' — '.$a->reference.' — terminée le '.date_fr($s->date_fin).' ('.$s->delaiFroid().' mois après : à partir du '.date_fr($partir).')'">
        <a class="btn btn-secondaire" href="{{ route('evaluations.index') }}">Retour aux évaluations</a>
    </x-entete>
    @if ($b?->resultat_attendu || $b?->objectif_vise)
        <section class="prochaine"><div><h2>Ce qui était attendu (demande {{ $b->reference }})</h2>
            @if ($b->resultat_attendu)<p class="whitespace-pre-line">{{ $b->resultat_attendu }}</p>@endif
            @if ($b->objectif_vise)<p class="detail">Mesure : aujourd'hui « {{ $b->situation_actuelle }} » ; objectif « {{ $b->objectif_vise }} ».</p>@endif</div></section>
    @endif
    @unless ($possible)
        <div class="message message-info mb-5"><x-icone nom="info" class="h-5 w-5 flex-none" /><div>L'évaluation sera possible à partir du {{ date_fr($partir) }}, le temps que les agents mettent en pratique ce qu'ils ont appris.</div></div>
    @endunless
    <x-carte sans-marge>
        <form method="POST" action="{{ route('evaluations.froid.enregistrer', $a) }}">
            @csrf
            <fieldset @disabled(! $possible)>
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Agent</th><th>Applique-t-il ce qu'il a appris ?</th><th>Commentaire</th></tr></thead>
                    <tbody>@foreach ($agents as $i)
                        @php $e = $i->evaluationsImpact->first(); $n = "lignes[{$i->id}]"; @endphp
                        <tr>
                            <td>{{ $i->agent->nom_complet }}<div class="text-xs text-encre-3">{{ $i->agent->matricule }}@if ($i->agent->service) — {{ $i->agent->service->nom }}@endif</div></td>
                            <td><fieldset class="flex flex-wrap gap-x-4 gap-y-1"><legend class="sr-only">{{ $i->agent->nom_complet }} applique-t-il ce qu'il a appris ?</legend>
                                @foreach (E::REPONSES as $v => $lib)<label class="font-normal flex gap-1.5 items-center whitespace-nowrap"><input type="radio" name="{{ $n }}[resultat]" value="{{ $v }}" @checked(old("lignes.{$i->id}.resultat", $e?->resultat_atteint) === $v)> {{ $lib }}</label>@endforeach
                            </fieldset></td>
                            <td><input type="text" name="{{ $n }}[commentaire]" maxlength="1000" value="{{ old("lignes.{$i->id}.commentaire", $e?->commentaire) }}" placeholder="Facultatif" aria-label="Commentaire sur {{ $i->agent->nom_complet }}"></td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            </fieldset>
            @if ($possible)<div class="flex gap-2 p-4"><button class="btn btn-primaire">Enregistrer</button><a class="btn btn-secondaire" href="{{ route('evaluations.index') }}">Annuler</a></div>@endif
        </form>
    </x-carte>
</x-layout>
