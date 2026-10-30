@php
    use App\Enums\StatutSession as S;
    $aVenir = $inscriptions->filter(fn ($i) => ! in_array($i->session->statut, [S::Terminee, S::Annulee], true) && in_array($i->statut->value, ['inscrit', 'convoque', 'confirme'], true))->sortBy(fn ($i) => $i->session->date_debut);
    $passees = $inscriptions->reject(fn ($i) => $aVenir->contains('id', $i->id));
@endphp
<x-layout titre="Mon espace">
    <x-entete titre="Mes formations" :sous-titre="$agent ? $agent->nom_complet.' — matricule '.$agent->matricule.($agent->service ? ' — '.$agent->service->nom : '') : null" />
    @if (! $agent)
        <x-carte><p class="text-sm">Votre compte n'est associé à aucune fiche du référentiel du personnel. Contactez la Direction des ressources humaines.</p></x-carte>
    @else
        <x-carte titre="À venir" class="mb-5" sans-marge>
            @if ($aVenir->isEmpty())
                <x-vide message="Aucune formation prévue pour vous en ce moment." />
            @else
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Formation</th><th>Dates</th><th>Horaires</th><th>Lieu</th><th>Convocation</th></tr></thead>
                    <tbody>@foreach ($aVenir as $i)
                        <tr>
                            <td>{{ $i->session->action->intitule }}</td>
                            <td class="whitespace-nowrap">{{ date_fr($i->session->date_debut) }} – {{ date_fr($i->session->date_fin) }}</td>
                            <td class="whitespace-nowrap">{{ $i->session->heure_debut ? substr((string) $i->session->heure_debut, 0, 5).' – '.substr((string) $i->session->heure_fin, 0, 5) : '—' }}</td>
                            <td>{{ $i->session->lieu ?? '—' }}</td>
                            <td>@if ($i->convoque_le)<a class="btn btn-primaire btn-petit" href="{{ route('espace.convocation', $i) }}">Télécharger ma convocation</a><div class="text-xs text-encre-3 mt-1">Reçue le {{ date_fr($i->convoque_le) }}</div>@else<span class="text-encre-3 text-sm">Pas encore envoyée</span>@endif</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </x-carte>
        <x-carte titre="Formations suivies et passées" sans-marge>
            @if ($passees->isEmpty())
                <x-vide message="Aucune formation enregistrée à votre nom." />
            @else
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Formation</th><th>Dates</th><th>Lieu</th><th>Suivie</th><th>Attestation</th></tr></thead>
                    <tbody>@foreach ($passees as $i)
                        <tr>
                            <td>{{ $i->session->action->intitule }}<div class="text-xs text-encre-3">{{ $i->session->action->domaine?->nom }}</div></td>
                            <td class="whitespace-nowrap">{{ date_fr($i->session->date_debut) }} – {{ date_fr($i->session->date_fin) }}</td>
                            <td>{{ $i->session->lieu ?? '—' }}</td>
                            <td>@if ($i->session->statut === S::Annulee)<x-badge texte="Formation annulée" couleur="rouge" />@elseif ($i->resultat === 'achevee')<x-badge texte="Oui" couleur="vert" />@elseif ($i->resultat === 'non_achevee')<x-badge texte="Non" couleur="gris" />@else<x-badge :statut="$i->statut" />@endif</td>
                            <td>@if ($i->attestation?->estValide())<x-badge texte="Délivrée" couleur="vert" /><div class="text-xs text-encre-3 mt-1">{{ $i->attestation->reference }} — à retirer auprès de la RH</div>@else — @endif</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </x-carte>
        @php $diplomantes = $agent->formationsDiplomantes()->orderByDesc('annee_debut')->get(); @endphp
        @if ($diplomantes->isNotEmpty())
            <x-carte titre="Mes formations diplômantes" class="mt-5" sans-marge>
                @include('diplomantes._liste', ['liste' => $diplomantes, 'liens' => false])
                <p class="p-3 text-xs text-encre-3 border-t border-filet">Une information à corriger ? Adressez-vous à la Direction des ressources humaines.</p>
            </x-carte>
        @endif
    @endif
</x-layout>
