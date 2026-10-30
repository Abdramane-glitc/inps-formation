<x-layout :titre="$agent->nom_complet">
    <x-entete :titre="$agent->nom_complet" :sous-titre="'Matricule '.$agent->matricule">
        <a class="btn btn-secondaire" href="{{ route('agents.historique-pdf', $agent) }}">Historique (PDF)</a>
        @permission('utilisateurs.gerer')
            @if ($agent->actif)
                @if (! $agent->user)
                    <x-action-form :url="route('agents.acces.generer')" :champs="['agents[]' => $agent->id]" libelle="Donner un code d'accès" classe="btn btn-secondaire" />
                @else
                    <x-action-form :url="route('agents.acces.nouveau-code', $agent)" libelle="Nouveau code d'accès" classe="btn btn-secondaire" confirmer="Donner un nouveau code à {{ $agent->nom_complet }} ?" />
                @endif
            @endif
        @endpermission
        @permission('agents.gerer')
            <a class="btn btn-secondaire" href="{{ route('agents.edit', $agent) }}">Modifier</a>
            @if ($agent->actif)
                <x-dialogue-motif id="desactiver" :url="route('agents.activation', $agent)" titre="Désactiver l'agent" libelle="Désactiver" :champs="['actif' => 0]">
                    <p class="text-sm">La fiche et l'historique sont conservés ; l'agent ne pourra plus être inscrit.</p>
                </x-dialogue-motif>
            @else
                <x-action-form :url="route('agents.activation', $agent)" libelle="Réactiver" :champs="['actif' => 1]" classe="btn btn-secondaire" />
            @endif
            @if (app(\App\Services\SuppressionService::class)->obstacleAgent($agent) === null)
                <x-action-form :url="route('suppression.unique', ['agents', $agent->id])" methode="DELETE" libelle="Supprimer" classe="btn btn-danger" confirmer="Supprimer définitivement cet agent ? À utiliser seulement pour une erreur de saisie : il n'a jamais été inscrit à une formation." />
            @endif
        @endpermission
    </x-entete>
    <div class="grid gap-5 lg:grid-cols-3">
        <x-carte titre="Fiche">
            <dl class="dl-grille">
                <dt>Statut</dt><dd>@if ($agent->actif)<x-badge texte="Actif" couleur="vert" />@else<x-badge texte="Inactif" couleur="gris" /> depuis le {{ date_fr($agent->date_desactivation) }}@endif</dd>
                @unless ($agent->actif)<dt>Motif</dt><dd>{{ $agent->motif_desactivation }}</dd>@endunless
                <dt>Direction</dt><dd>{{ $agent->direction?->nom ?? '—' }}</dd>
                <dt>Service</dt><dd>{{ $agent->service?->nom ?? '—' }}</dd>
                <dt>Fonction</dt><dd>{{ $agent->fonction ?? '—' }}</dd>
                <dt>Statut prof.</dt><dd>{{ $agent->statutProfessionnel?->libelle ?? '—' }}</dd>
                <dt>E-mail pro.</dt><dd>{{ $agent->email_pro ?? '—' }}</dd>
                <dt>Téléphone</dt><dd>{{ $agent->telephone_pro ?? '—' }}</dd>
                <dt>Origine</dt><dd>{{ ['saisie' => 'Saisie manuelle', 'import' => 'Import', 'sirh' => 'SIRH'][$agent->source] ?? $agent->source }}</dd>
                <dt>Accès à l'application</dt><dd>@if (! $agent->user) Aucun @elseif ($agent->user->aCodeValide()) À activer @permission('utilisateurs.gerer')— code <span class="font-medium tabular-nums font-semibold">{{ $agent->user->code_activation_affiche }}</span> (jusqu'au {{ date_fr($agent->user->code_activation_expire_le) }})@endpermission @elseif ($agent->user->derniere_connexion_le) Activé (dernière connexion le {{ date_fr($agent->user->derniere_connexion_le) }}) @else Compte créé, sans code valable @endif</dd>
            </dl>
        </x-carte>
        <x-carte titre="Historique des formations" class="lg:col-span-2" sans-marge>
            @if ($inscriptions->isEmpty())
                <x-vide message="Aucune formation enregistrée pour cet agent." />
            @else
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Formation</th><th>Dates</th><th>Type</th><th>Inscription</th><th>A suivi la formation</th><th>Attestation</th></tr></thead>
                    <tbody>
                    @foreach ($inscriptions as $i)
                        <tr>
                            <td>@permission('actions.voir')<a href="{{ route('formations.show', $i->session->action_id) }}">{{ $i->session->action->intitule }}</a>@else{{ $i->session->action->intitule }}@endpermission<div class="text-xs text-slate-500">{{ $i->session->code }}</div></td>
                            <td class="whitespace-nowrap">{{ date_fr($i->session->date_debut) }} – {{ date_fr($i->session->date_fin) }}</td>
                            <td>{{ ucfirst($i->session->action->type) }}</td>
                            <td><x-badge :statut="$i->statut" /></td>
                            <td>{{ $i->resultat === 'achevee' ? 'Oui' : ($i->resultat === 'non_achevee' ? 'Non' : '—') }}</td>
                            <td>{{ $i->attestation?->estValide() ? $i->attestation->reference : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-carte>
    </div>
    @permission('diplomantes.gerer')
        <x-carte titre="Formations diplômantes" class="mt-5" sans-marge>
            @php $diplomantes = $agent->formationsDiplomantes()->orderByDesc('annee_debut')->get(); @endphp
            @if ($diplomantes->isEmpty())<x-vide message="Aucune formation diplômante enregistrée." />@else @include('diplomantes._liste', ['liste' => $diplomantes, 'liens' => true]) @endif
            <div class="p-3 border-t border-filet no-print"><a class="btn btn-secondaire btn-petit" href="{{ route('diplomantes.create', ['agent' => $agent->id]) }}">Ajouter une formation diplômante</a></div>
        </x-carte>
    @endpermission
</x-layout>
