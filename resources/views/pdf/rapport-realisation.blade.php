@php
    use App\Models\EvaluationAcquis as E;
    $b = $a->besoin;
    $note = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '').' / 5';
@endphp
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')<style> p, li { font-size: 9.5pt; line-height: 1.45; } .texte { white-space: pre-line; } </style></head><body>
@include('pdf._pied')
@include('pdf._entete', ['titre' => 'Rapport de réalisation de formation'])

<h2>1. La formation</h2>
<table>
    <tr><th style="width: 30%">Intitulé</th><td>{{ $a->intitule }} ({{ $a->reference }})</td></tr>
    <tr><th>Dates et lieu</th><td>Du {{ $s->date_debut->format('d/m/Y') }} au {{ $s->date_fin->format('d/m/Y') }}@if ($s->lieu) — {{ $s->lieu }}@endif @if ($s->duree_heures) — {{ rtrim(rtrim(number_format((float) $s->duree_heures, 1, ',', ''), '0'), ',') }} heures @endif</td></tr>
    <tr><th>Type</th><td>{{ \App\Models\ActionFormation::TYPES[$a->type] ?? $a->type }}@if ($a->organisme) — {{ $a->organisme->raison_sociale }}@endif</td></tr>
    <tr><th>Formateur(s)</th><td>{{ $s->formateurs->map(fn ($f) => $f->libelleComplet())->implode(', ') ?: '—' }}</td></tr>
    @if ($b)<tr><th>Demande d'origine</th><td>{{ $b->reference }} — {{ $b->service?->nom }}</td></tr>@endif
</table>

@if ($b && ($b->contexte || $b->resultat_attendu))
<h2>2. Le besoin</h2>
<table>
    @if ($b->contexte)<tr><th style="width: 30%">Problème constaté</th><td class="texte">{{ $b->contexte }}</td></tr>@endif
    @if ($b->situation_actuelle)<tr><th>Mesure</th><td>Avant : {{ $b->situation_actuelle }} — objectif : {{ $b->objectif_vise }}</td></tr>@endif
    @if ($b->resultat_attendu)<tr><th>Résultat attendu</th><td class="texte">{{ $b->resultat_attendu }}</td></tr>@endif
</table>
@endif

<h2>{{ $b ? '3' : '2' }}. Participation</h2>
<p>{{ $suivis }} participant(s) ont suivi la formation sur {{ $inscrits }} inscrit(s)@if ($inscrits) — taux de participation {{ pourcent(100 * $suivis / $inscrits) }}@endif.</p>
@if ($parService->count() > 1)
<table><thead><tr><th>Service</th><th class="droite">Inscrits</th><th class="droite">Ont suivi</th></tr></thead>
    <tbody>@foreach ($parService as $nom => [$i, $sv])<tr><td>{{ $nom }}</td><td class="droite">{{ $i }}</td><td class="droite">{{ $sv }}</td></tr>@endforeach</tbody></table>
@endif

<h2>{{ $b ? '4' : '3' }}. Ont-ils compris ? (en fin de formation)</h2>
@if ($chaud['faites'])
<table><thead><tr><th>Réponse</th><th class="droite" style="width: 25%">Participants</th></tr></thead>
    <tbody>@foreach (E::REPONSES as $k => $l)<tr><td>{{ $l }}</td><td class="droite">{{ $chaud['reponses'][$k] }}</td></tr>@endforeach</tbody></table>
<p>{{ $chaud['faites'] }} participant(s) évalué(s) sur {{ $chaud['attendues'] }}@if ($chaud['note_moyenne'] !== null) — note moyenne {{ number_format($chaud['note_moyenne'], 1, ',', '') }} / {{ E::NOTE_MAX }}@endif.</p>
@else
<p>Pas encore indiqué.</p>
@endif

<h2>{{ $b ? '5' : '4' }}. Déroulement</h2>
<p class="texte">{{ $s->rapport_deroulement ?: 'Non rédigé.' }}</p>
<h2>{{ $b ? '6' : '5' }}. Difficultés rencontrées</h2>
<p class="texte">{{ $s->rapport_difficultes ?: 'Aucune signalée.' }}</p>
<h2>{{ $b ? '7' : '6' }}. Recommandations</h2>
<p class="texte">{{ $s->rapport_recommandations ?: 'Aucune.' }}</p>

<h2>{{ $b ? '8' : '7' }}. Appliquent-ils ce qu'ils ont appris ? ({{ $s->delaiFroid() }} mois après)</h2>
@if ($froid['faites'])
<p>{{ $froid['faites'] }} agent(s) évalué(s) sur {{ $froid['attendues'] }} par leur responsable de structure : @foreach (E::REPONSES as $k => $l){{ $l }} : {{ $froid['reponses'][$k] }}@if (! $loop->last) ; @endif @endforeach.</p>
@else
<p>Prévue à partir du {{ $s->dateEvaluationFroid()->format('d/m/Y') }}, par le responsable de structure de chaque agent.</p>
@endif

<p class="petit" style="margin-top: 16px">Rapport rédigé@if ($s->redacteurRapport) par {{ $s->redacteurRapport->name }}@endif @if ($s->rapport_redige_le) le {{ $s->rapport_redige_le->format('d/m/Y') }}@endif.</p>
</body></html>
