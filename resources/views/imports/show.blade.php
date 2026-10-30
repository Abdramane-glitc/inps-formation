<x-layout titre="Import">
    <x-entete :titre="'Import « '.$import->nom_fichier.' »'" :sous-titre="($import->type === 'agents' ? 'Agents' : 'Historique des formations').' — analysé le '.date_fr($import->created_at, true)">
        @if ($import->statut === 'analyse')
            <x-action-form :url="route('imports.abandonner', $import)" libelle="Abandonner" classe="btn btn-secondaire" />
            @if ($import->lignes_valides > 0)
                <x-action-form :url="route('imports.confirmer', $import)" :libelle="'Confirmer l\'import de '.$import->lignes_valides.' ligne(s) valide(s)'" classe="btn btn-primaire" :confirmer="'Écrire '.$import->lignes_valides.' ligne(s) dans la base ? Les lignes en erreur ou en doublon seront ignorées.'" />
            @endif
        @endif
    </x-entete>
    <div class="grid gap-4 grid-cols-2 md:grid-cols-5 mb-5">
        <x-kpi libelle="Lignes lues" :valeur="$import->total_lignes" />
        <x-kpi libelle="Valides" :valeur="$import->lignes_valides" />
        <x-kpi libelle="En erreur" :valeur="$import->lignes_erreur" />
        <x-kpi libelle="Doublons" :valeur="$import->lignes_doublon" />
        <x-kpi libelle="Importées" :valeur="$import->lignes_importees" :detail="['analyse' => 'En attente de confirmation', 'termine' => 'Terminé le '.date_fr($import->termine_le, true), 'echoue' => 'Échec', 'abandonne' => 'Abandonné'][$import->statut]" />
    </div>
    @if ($import->message)<div class="mb-4 text-sm carte p-3">{{ $import->message }}</div>@endif
    <x-carte titre="Détail des lignes" sans-marge>
        <x-slot:actions>
            @foreach (['' => 'Toutes', 'valide' => 'Valides', 'erreur' => 'Erreurs', 'doublon' => 'Doublons', 'importee' => 'Importées'] as $k => $l)
                <a href="{{ route('imports.show', [$import, 'statut' => $k ?: null]) }}" class="btn btn-secondaire btn-petit {{ ($statut ?? '') === $k ? 'ring-2 ring-primaire' : '' }}">{{ $l }}</a>
            @endforeach
        </x-slot:actions>
        @if ($lignes->isEmpty())
            <x-vide message="Aucune ligne." />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Ligne</th><th>Statut</th><th>Données</th><th>Messages</th></tr></thead>
                <tbody>@foreach ($lignes as $l)
                    <tr>
                        <td>{{ $l->numero }}</td>
                        <td><x-badge :texte="['valide' => 'Valide', 'erreur' => 'Erreur', 'doublon' => 'Doublon', 'importee' => 'Importée'][$l->statut]" :couleur="['valide' => 'bleu', 'erreur' => 'rouge', 'doublon' => 'orange', 'importee' => 'vert'][$l->statut]" /></td>
                        <td class="text-xs">{{ collect($l->donnees)->filter()->map(fn ($v, $k) => "$k : $v")->implode(' · ') }}</td>
                        <td class="text-xs">{{ implode(' ', $l->messages ?? []) }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $lignes->links() }}
        @endif
    </x-carte>
</x-layout>
