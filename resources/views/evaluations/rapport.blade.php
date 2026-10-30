<x-layout titre="Rapport de réalisation">
    <x-entete :titre="'Rapport de réalisation — '.$a->intitule" :sous-titre="$a->reference.' — du '.date_fr($s->date_debut).' au '.date_fr($s->date_fin)">
        @if ($s->rapportRedige())<a class="btn btn-secondaire" href="{{ route('rapport-realisation.pdf', $a) }}">Télécharger le rapport (PDF)</a>@endif
        <a class="btn btn-secondaire" href="{{ route('formations.show', $a) }}">Retour à la formation</a>
    </x-entete>
    <div class="grid gap-5 lg:grid-cols-3">
        <x-carte titre="Repris automatiquement" class="lg:col-span-1">
            <dl class="dl-grille">
                <dt>Participants</dt><dd>{{ $suivis }} ont suivi sur {{ $inscrits }} inscrit(s)@if ($inscrits) ({{ pourcent(100 * $suivis / $inscrits) }})@endif</dd>
                <dt>Formateur(s)</dt><dd>{{ $s->formateurs->map(fn ($f) => $f->libelleComplet())->implode(', ') ?: '—' }}</dd>
                <dt>Ont-ils compris ?</dt><dd>@if ($chaud['faites'])@foreach (\App\Models\EvaluationAcquis::REPONSES as $k => $l){{ $l }} : {{ $chaud['reponses'][$k] }}@if (! $loop->last), @endif @endforeach @else pas encore indiqué @endif</dd>
                <dt>Appliquent-ils ?</dt><dd>{{ $froid['faites'] }} évaluation(s) sur {{ $froid['attendues'] }}, à partir du {{ date_fr($s->dateEvaluationFroid()) }}</dd>
            </dl>
            <p class="aide mt-3">Ces éléments, les dates, le lieu, la demande d'origine et le détail par service figurent dans le PDF.</p>
        </x-carte>
        <x-carte titre="À rédiger" class="lg:col-span-2">
            <form method="POST" action="{{ route('rapport-realisation.update', $a) }}" class="space-y-4">
                @csrf @method('PUT')
                <x-champ nom="rapport_deroulement" libelle="Déroulement de la formation" type="textarea" :valeur="$s->rapport_deroulement" :requis="true" aide="Ce qui a été fait : programme suivi, méthodes, ambiance, travaux réalisés." />
                <x-champ nom="rapport_difficultes" libelle="Difficultés rencontrées" type="textarea" :valeur="$s->rapport_difficultes" aide="Absences, retards, problèmes de salle ou de matériel, contenu trop dense…" />
                <x-champ nom="rapport_recommandations" libelle="Recommandations" type="textarea" :valeur="$s->rapport_recommandations" aide="Suites à donner : complément de formation, autre groupe, changement de prestataire…" />
                <button class="btn btn-primaire">Enregistrer le rapport</button>
                @if ($s->rapportRedige())<p class="aide">Dernière mise à jour le {{ date_fr($s->rapport_redige_le, true) }}@if ($s->redacteurRapport) par {{ $s->redacteurRapport->name }}@endif.</p>@endif
            </form>
        </x-carte>
    </div>
</x-layout>
