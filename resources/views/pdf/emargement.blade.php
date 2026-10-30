<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')<style> td.signature { height: 26px; } </style></head><body>
@include('pdf._pied')
@include('pdf._entete', ['titre' => "Liste d'émargement — ".$s->action->intitule])
<p>Session {{ $s->code }} — du {{ $s->date_debut->format('d/m/Y') }} au {{ $s->date_fin->format('d/m/Y') }}@if ($s->lieu) — {{ $s->lieu }}@endif @if ($s->formateurs->isNotEmpty()) — {{ $s->formateurs->count() > 1 ? 'Formateurs' : 'Formateur' }} : {{ $s->formateurs->map(fn ($f) => $f->nom_complet)->implode(', ') }}@endif</p>
@foreach ($s->seances->chunk(5) as $groupe)
<table @if (! $loop->last) class="saut" @endif>
    <thead><tr><th style="width: 20px">N°</th><th>Matricule</th><th>Nom et prénom</th><th>Service</th>@foreach ($groupe as $se)<th style="width: 90px">{{ $se->libelle }}<br>{{ $se->date->format('d/m/Y') }}</th>@endforeach</tr></thead>
    <tbody>@foreach ($participants->values() as $n => $i)
        <tr><td>{{ $n + 1 }}</td><td>{{ $i->agent->matricule }}</td><td>{{ $i->agent->nom_complet }}</td><td>{{ $i->agent->service?->nom }}</td>@foreach ($groupe as $se)<td class="signature"></td>@endforeach</tr>
    @endforeach</tbody>
</table>
@endforeach
<p class="petit" style="margin-top: 12px">Visa du ou des formateurs : ____________________ &nbsp;&nbsp; Visa du responsable formation : ____________________</p>
</body></html>
