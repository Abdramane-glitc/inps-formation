@php
    use App\Models\PlanLigne;
    $trimestres = ['T1' => '1er trimestre', 'T2' => '2e trimestre', 'T3' => '3e trimestre', 'T4' => '4e trimestre', 'annee' => 'Dans l\'année (période à préciser)'];
@endphp
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._pied')
@include('pdf._entete', ['titre' => 'Plan annuel de formation '.$annee])
<p>@if ($plan?->estValide())Plan validé le {{ $plan->valide_le->format('d/m/Y') }} par {{ $plan->validePar?->name }}.@else Plan en préparation (non validé).@endif
    {{ $parTrimestre->flatten()->count() }} formation(s) prévue(s).</p>
@foreach ($trimestres as $t => $titreT)
    @continue(! isset($parTrimestre[$t]))
    <h3 style="margin: 10px 0 4px">{{ $titreT }}</h3>
    <table>
        <thead><tr><th style="width: 80px">Période</th><th>Formation prévue</th><th>Service</th><th style="width: 45px">Agents</th><th>Type / organisme</th><th style="width: 70px">Demande</th><th style="width: 140px">État</th></tr></thead>
        <tbody>@foreach ($parTrimestre[$t] as $l)
            <tr><td>{{ $l->libellePeriode() }}</td><td>{{ $l->intitule }}</td><td>{{ $l->service?->nom ?? 'Plusieurs services' }}</td><td>{{ $l->nb_agents }}</td>
                <td>{{ $l->type ? ucfirst($l->type) : '' }}{{ $l->organisme ? ' — '.$l->organisme->raison_sociale : '' }}</td><td>{{ $l->besoin?->reference }}</td>
                <td>{{ PlanLigne::ETATS[$l->etat()] }}@if ($l->formation?->session) — {{ $l->formation->session->date_debut->format('d/m/Y') }}@endif</td></tr>
        @endforeach</tbody>
    </table>
@endforeach
</body></html>
