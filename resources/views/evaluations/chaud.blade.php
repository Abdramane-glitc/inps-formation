@php use App\Models\EvaluationAcquis as E; @endphp
<x-layout titre="Ont-ils compris ?">
    <x-entete :titre="'Ont-ils compris la formation ?'" :sous-titre="$a->intitule.' — '.$a->reference.' — du '.date_fr($s->date_debut).' au '.date_fr($s->date_fin)">
        <a class="btn btn-secondaire" href="{{ route('formations.show', $a) }}">Retour à la formation</a>
    </x-entete>
    <div class="message message-info mb-5"><x-icone nom="info" class="h-5 w-5 flex-none" /><div>
        Pour chaque participant, indiquez s'il a compris la formation (après un test, un exercice ou un échange en fin de formation).
        La note sur {{ E::NOTE_MAX }} et le commentaire sont facultatifs. Une ligne laissée vide n'est pas enregistrée.
    </div></div>
    <x-carte sans-marge>
        @if ($participants->isEmpty())
            <x-vide message="Aucun participant n'a suivi la formation." />
        @else
            <form method="POST" action="{{ route('evaluations.chaud.enregistrer', $a) }}">
                @csrf
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Participant</th><th>A-t-il compris ?</th><th class="w-28">Note /{{ E::NOTE_MAX }}</th><th>Commentaire</th></tr></thead>
                    <tbody>@foreach ($participants as $i)
                        @php $e = $i->evaluationAcquis; $n = "lignes[{$i->id}]"; $k = "lignes.{$i->id}"; @endphp
                        <tr>
                            <td>{{ $i->agent->nom_complet }}<div class="text-xs text-encre-3">{{ $i->agent->matricule }}@if ($i->agent->service) — {{ $i->agent->service->nom }}@endif</div></td>
                            <td><fieldset class="flex flex-wrap gap-x-4 gap-y-1"><legend class="sr-only">{{ $i->agent->nom_complet }} a-t-il compris ?</legend>
                                @foreach (E::REPONSES as $v => $lib)<label class="font-normal flex gap-1.5 items-center whitespace-nowrap"><input type="radio" name="{{ $n }}[resultat]" value="{{ $v }}" @checked(old("$k.resultat", $e?->resultat) === $v)> {{ $lib }}</label>@endforeach
                            </fieldset></td>
                            <td><input type="number" name="{{ $n }}[note]" min="0" max="{{ E::NOTE_MAX }}" step="0.5" value="{{ old("$k.note", $e?->score !== null ? rtrim(rtrim((string) $e->score, '0'), '.') : null) }}" aria-label="Note de {{ $i->agent->nom_complet }}" @if ($errors->has("$k.note")) aria-invalid="true" @endif></td>
                            <td><input type="text" name="{{ $n }}[commentaire]" maxlength="1000" value="{{ old("$k.commentaire", $e?->appreciation) }}" aria-label="Commentaire sur {{ $i->agent->nom_complet }}"></td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
                @if ($errors->any())<p class="erreur-champ px-4"><x-icone nom="erreur" class="h-3.5 w-3.5 mt-0.5" />{{ $errors->first() }}</p>@endif
                <div class="flex gap-2 p-4"><button class="btn btn-primaire">Enregistrer</button><a class="btn btn-secondaire" href="{{ route('formations.show', $a) }}">Annuler</a></div>
            </form>
        @endif
    </x-carte>
</x-layout>
