@php
    $f = $att->formateur;
    $duree = $s->duree_heures ? ', soit '.rtrim(rtrim(number_format((float) $s->duree_heures, 1, ',', ''), '0'), ',').' heures' : '';
    $lieu = $s->lieu ? ' à '.$s->lieu : '';
@endphp
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')
<style> .cadre { border: 3px double #003B73; padding: 28px 34px; margin-top: 20px; } .centre { text-align: center; } .grand { font-size: 20pt; letter-spacing: 1px; } p { line-height: 1.6; font-size: 11pt; } </style></head><body>
@include('pdf._pied')
@include('pdf._entete')
{{-- Modèle proposé le 26/10/2026, à valider par l'INPS (docs/DECISIONS_A_VALIDER.md) --}}
<div class="cadre">
    <p class="centre grand"><strong>ATTESTATION D'INTERVENTION</strong></p>
    <p class="centre petit">Référence : {{ $att->reference }}</p>
    <p>La {{ $institution['direction'] }} ({{ $institution['nom'] }}) atteste que <strong>{{ $f->prenom }} {{ mb_strtoupper($f->nom) }}</strong>@if ($f->organisme), du cabinet ou organisme <strong>{{ $f->organisme->raison_sociale }}</strong>@endif,
        est intervenu(e) en qualité de formateur dans la formation <strong>« {{ $s->action->intitule }} »</strong>, organisée du {{ $s->date_debut->format('d/m/Y') }} au {{ $s->date_fin->format('d/m/Y') }}{{ $lieu }}{{ $duree }},
        au profit de {{ $participants }} agent(s) de l'Institut.</p>
    <p>La présente attestation est délivrée à l'intéressé(e) pour servir et valoir ce que de droit.</p>
    <p style="margin-top: 30px">Fait le {{ $att->emise_le->format('d/m/Y') }}</p>
    <p style="margin-top: 10px; text-align: right"><strong>{{ \App\Models\Parametre::valeur('institution.signataire_attestation') ?: 'Le signataire habilité (à configurer)' }}</strong></p>
    <p style="height: 60px"></p>
</div>
</body></html>
