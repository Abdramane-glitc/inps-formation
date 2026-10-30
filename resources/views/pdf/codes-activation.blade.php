<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">@include('pdf._style')<style> .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 12pt; font-weight: bold; letter-spacing: 1px; } </style></head><body>
@include('pdf._pied')
@include('pdf._entete', ['titre' => 'Codes d\'activation des agents'])
<p>Pour activer son compte : ouvrir <strong>{{ config('app.url') }}</strong>, cliquer sur « Activer mon compte », saisir son matricule et son code,
    puis choisir son mot de passe. Chaque code sert une seule fois. Document confidentiel : à remettre à chaque agent, puis à détruire.</p>
<table>
    <thead><tr><th>Matricule</th><th>Agent</th><th>Service</th><th>Code</th><th>Valable jusqu'au</th></tr></thead>
    <tbody>@forelse ($agents as $ag)
        <tr><td>{{ $ag->matricule }}</td><td>{{ $ag->nom_complet }}</td><td>{{ $ag->service?->nom }}</td><td class="code">{{ $ag->user->code_activation_affiche }}</td><td>{{ $ag->user->code_activation_expire_le->format('d/m/Y') }}</td></tr>
    @empty
        <tr><td colspan="5">Aucun code en attente pour ces critères.</td></tr>
    @endforelse</tbody>
</table>
</body></html>
