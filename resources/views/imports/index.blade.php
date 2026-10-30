<x-layout titre="Importations">
    <x-entete titre="Importation et reprise des données" sous-titre="Structure, personnel et historique des formations — analyse, aperçu et confirmation avant toute écriture" />
    <div class="grid gap-5 lg:grid-cols-2 mb-5">
        <x-carte titre="Nouvel import">
            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-champ nom="type" libelle="Type de données" type="select" :options="$libelles" :vide="false" :requis="true" aide="Commencez par la structure (directions et services), puis le personnel, puis l'historique." />
                <div><label for="fichier">Fichier CSV (séparateur « ; » ou « , ») ou Excel (.xlsx), 5 Mo max.</label><input id="fichier" type="file" name="fichier" required accept=".csv,.txt,.xlsx"></div>
                <button class="btn btn-primaire">Analyser le fichier</button>
                <p class="aide">L'analyse n'écrit rien dans le référentiel. Les lignes en doublon ou en erreur sont signalées puis ignorées ; aucune donnée existante n'est modifiée ou supprimée.</p>
            </form>
        </x-carte>
        <x-carte titre="Modèles de fichiers">
            @foreach ($colonnes as $type => $c)
                <div class="mb-4 text-sm">
                    <p class="font-medium">{{ $libelles[$type] }} — <a href="{{ route('imports.modele', $type) }}">télécharger le modèle CSV</a></p>
                    <p>Colonnes obligatoires : <code>{{ implode(', ', $c['obligatoires']) }}</code></p>
                    <p>Colonnes facultatives : <code>{{ implode(', ', $c['facultatives']) }}</code></p>
                </div>
            @endforeach
            <p class="aide">Structure : une ligne par service avec sa direction, ou seulement la liste des directions (colonnes service vides) ; la liste des directions extraite du système du personnel (UA_CODE, DESIGNATION, SIGLE) et la liste du personnel (UACODE_DIRECTION, FONCTION) sont reconnues telles quelles, leurs autres colonnes (comptes bancaires, n° INPS…) sont ignorées et jamais enregistrées ; un fichier Excel peut avoir plusieurs feuilles, seule la feuille ouverte à l'enregistrement est lue. Pour le personnel et l'historique, les codes de direction, service, domaine et statut doivent exister dans les référentiels. Dates : JJ/MM/AAAA ou AAAA-MM-JJ. Résultat : achevee ou non_achevee.</p>
        </x-carte>
    </div>
    <x-carte titre="Imports précédents" sans-marge>
        @if ($imports->isEmpty())
            <x-vide message="Aucun import réalisé." />
        @else
            <div class="overflow-x-auto"><table>
                <thead><tr><th>Date</th><th>Type</th><th>Fichier</th><th>Statut</th><th class="text-right">Lignes</th><th class="text-right">Valides</th><th class="text-right">Erreurs</th><th class="text-right">Doublons</th><th class="text-right">Importées</th><th>Par</th></tr></thead>
                <tbody>@foreach ($imports as $i)
                    <tr><td><a href="{{ route('imports.show', $i) }}">{{ date_fr($i->created_at, true) }}</a></td><td>{{ $libelles[$i->type] ?? $i->type }}</td><td>{{ $i->nom_fichier }}</td><td>{{ ['analyse' => 'À confirmer', 'termine' => 'Terminé', 'echoue' => 'Échec', 'abandonne' => 'Abandonné'][$i->statut] }}</td>
                        <td class="text-right">{{ $i->total_lignes }}</td><td class="text-right">{{ $i->lignes_valides }}</td><td class="text-right">{{ $i->lignes_erreur }}</td><td class="text-right">{{ $i->lignes_doublon }}</td><td class="text-right">{{ $i->lignes_importees }}</td><td>{{ $i->user->name }}</td></tr>
                @endforeach</tbody>
            </table></div>
            {{ $imports->links() }}
        @endif
    </x-carte>
</x-layout>
