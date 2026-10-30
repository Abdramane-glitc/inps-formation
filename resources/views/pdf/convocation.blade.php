@php
    $a = $s->action;
    $heures = $s->heure_debut ? 'de '.substr((string) $s->heure_debut, 0, 5).' à '.substr((string) $s->heure_fin, 0, 5) : 'à préciser';
@endphp
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')
<style> p { font-size: 11pt; line-height: 1.6; } td, th { font-size: 10.5pt; } .titre { text-align: center; font-size: 18pt; letter-spacing: 1px; margin: 18px 0 4px; } </style></head><body>
@include('pdf._pied')
{{-- Modèle proposé le 27/10/2026, à valider par l'INPS (docs/DECISIONS_A_VALIDER.md) --}}
@foreach ($inscriptions as $i)
<div @if (! $loop->last) class="saut" @endif>
@include('pdf._entete')
<p class="titre"><strong>{{ $rectificative ? 'CONVOCATION RECTIFICATIVE' : 'CONVOCATION' }}</strong></p>
<p style="text-align: center" class="petit">Formation {{ $a->reference }}</p>
<p>À : <strong>{{ $i->agent->prenom }} {{ mb_strtoupper($i->agent->nom) }}</strong>, matricule {{ $i->agent->matricule }}@if ($i->agent->service), {{ $i->agent->service->nom }}@endif</p>
<p>Vous êtes convoqué(e) à la formation <strong>« {{ $a->intitule }} »</strong>, aux dates et au lieu indiqués ci-dessous.@if ($rectificative) Cette convocation remplace la précédente : les dates, les horaires ou le lieu ont changé.@endif</p>
<table>
    <tr><th style="width: 28%">Dates</th><td>Du {{ $s->date_debut->format('d/m/Y') }} au {{ $s->date_fin->format('d/m/Y') }}</td></tr>
    <tr><th>Horaires</th><td>{{ $heures }}</td></tr>
    <tr><th>Lieu</th><td>{{ $s->lieu ?: 'à préciser' }}</td></tr>
    @if ($s->formateurs->isNotEmpty())<tr><th>Formateur(s)</th><td>{{ $s->formateurs->map(fn ($f) => $f->nom_complet)->implode(', ') }}</td></tr>@endif
    @if ($a->organisme)<tr><th>Organisme</th><td>{{ $a->organisme->raison_sociale }}</td></tr>@endif
</table>
<p style="margin-top: 14px">Nous vous prions de vous présenter à l'heure indiquée. Votre responsable de structure est informé de cette convocation.
    En cas d'empêchement, prévenez sans délai la {{ $institution['direction'] }}.</p>
<p style="margin-top: 24px">Fait le {{ now()->format('d/m/Y') }}</p>
<p style="text-align: right"><strong>{{ \App\Models\Parametre::valeur('institution.signataire_attestation') ?: 'Le signataire habilité (à configurer)' }}</strong></p>
</div>
@endforeach
</body></html>
