<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._pied')
@include('pdf._entete', ['titre' => 'Historique des formations'])
<p><strong>{{ $agent->nom_complet }}</strong> — matricule {{ $agent->matricule }} — {{ $agent->service?->nom }} {{ $agent->fonction ? '— '.$agent->fonction : '' }}</p>
<table>
    <thead><tr><th>Formation</th><th>Domaine</th><th>Type</th><th>Dates</th><th>Inscription</th><th>A suivi</th></tr></thead>
    <tbody>@forelse ($inscriptions as $i)
        <tr><td>{{ $i->session->action->intitule }}</td><td>{{ $i->session->action->domaine?->nom }}</td><td>{{ ucfirst($i->session->action->type) }}</td>
            <td>{{ $i->session->date_debut->format('d/m/Y') }} – {{ $i->session->date_fin->format('d/m/Y') }}</td><td>{{ $i->statut->libelle() }}</td>
            <td>{{ $i->resultat === 'achevee' ? 'Oui' : ($i->resultat === 'non_achevee' ? 'Non' : '—') }}</td></tr>
    @empty <tr><td colspan="6">Aucune formation enregistrée.</td></tr> @endforelse</tbody>
</table>
</body></html>
