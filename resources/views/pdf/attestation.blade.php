@php $i = $a->inscription; $s = $i->session; @endphp
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')
<style> .cadre { border: 3px double #003B73; padding: 28px 34px; margin-top: 20px; } .centre { text-align: center; } .grand { font-size: 20pt; letter-spacing: 1px; } p { line-height: 1.6; font-size: 11pt; } </style></head><body>
@include('pdf._pied')
@include('pdf._entete')
<div class="cadre">
    <p class="centre grand"><strong>ATTESTATION DE PARTICIPATION</strong></p>
    <p class="centre petit">Référence : {{ $a->reference }}</p>
    @php
        $lieu = $s->lieu ? ' à '.$s->lieu : '';
        $duree = $s->duree_heures ? ', d\'une durée de '.rtrim(rtrim(number_format((float) $s->duree_heures, 1, ',', ''), '0'), ',').' heures' : '';
        $fonction = $i->agent->fonction ? ', '.$i->agent->fonction : '';
    @endphp
    <p>Il est attesté que <strong>{{ $i->agent->prenom }} {{ mb_strtoupper($i->agent->nom) }}</strong>, matricule {{ $i->agent->matricule }}{{ $fonction }},
        a suivi la formation <strong>« {{ $s->action->intitule }} »</strong>, organisée du {{ $s->date_debut->format('d/m/Y') }} au {{ $s->date_fin->format('d/m/Y') }}{{ $lieu }}{{ $duree }},
        et a satisfait aux conditions de participation requises.</p>
    <p>La présente attestation est délivrée pour servir et valoir ce que de droit.</p>
    <p style="margin-top: 30px">Fait le {{ $a->emise_le->format('d/m/Y') }}</p>
    <p style="margin-top: 10px; text-align: right"><strong>{{ \App\Models\Parametre::valeur('institution.signataire_attestation') ?: 'Le signataire habilité (à configurer)' }}</strong></p>
    <p style="height: 60px"></p>
    <p class="petit">Code de vérification : {{ strtoupper(substr($a->code_verification, 0, 16)) }} — vérifiable auprès de la {{ $institution['direction'] }}.</p>
</div>
</body></html>
