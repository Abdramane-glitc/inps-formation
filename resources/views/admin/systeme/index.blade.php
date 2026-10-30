<x-layout titre="Sauvegardes">
    <x-entete titre="Sauvegardes" sous-titre="Une sauvegarde contient toute la base et les documents déposés. Elle se fait aussi automatiquement chaque nuit si la tâche planifiée est installée.">
        <x-action-form :url="route('admin.systeme.sauvegarde')" libelle="Faire une sauvegarde" classe="btn btn-primaire" confirmer="Faire une sauvegarde complète maintenant (base et documents) ?" />
    </x-entete>

    <section class="carte p-4 mb-5 {{ $toutVaBien ? 'border-l-4 border-l-ok' : 'border-l-4 border-l-alerte' }}">
        <h2 class="text-lg font-semibold {{ $toutVaBien ? 'text-ok' : 'text-alerte' }}">{{ $toutVaBien ? 'Tout va bien' : 'À vérifier' }}</h2>
        <ul class="mt-2 space-y-1.5 text-sm">
            @foreach ($points as [$libelle, $ok, $explication])
                <li class="flex gap-2"><span class="{{ $ok ? 'text-ok' : 'text-alerte' }} font-semibold w-4 flex-none" aria-hidden="true">{{ $ok ? '✓' : '!' }}</span>
                    <span><span class="sr-only">{{ $ok ? 'Conforme :' : 'À vérifier :' }}</span>{{ $libelle }}@if (! $ok || $loop->first)<span class="block text-encre-2">{{ $explication }}</span>@endif</span></li>
            @endforeach
        </ul>
    </section>

    <x-carte titre="Sauvegardes disponibles ({{ count($sauvegardes) }})" sans-marge class="mb-5">
        @if (empty($sauvegardes))<x-vide message="Aucune sauvegarde pour l'instant." />@else
            <div class="overflow-x-auto"><table><thead><tr><th>Date</th><th class="text-right">Taille</th><th class="no-print"></th></tr></thead>
                <tbody>@foreach ($sauvegardes as $s)<tr>
                    <td>{{ date('d/m/Y à H:i', $s['date']) }} <span class="text-xs text-encre-3 font-mono">{{ $s['fichier'] }}</span></td>
                    <td class="text-right tabular-nums">{{ $s['taille'] >= 1048576 ? number_format($s['taille'] / 1048576, 1, ',', ' ').' Mo' : number_format($s['taille'] / 1024, 0, ',', ' ').' Ko' }}</td>
                    <td class="text-right no-print"><a class="btn btn-secondaire btn-petit" href="{{ route('admin.systeme.telecharger', $s['fichier']) }}">Télécharger</a></td>
                </tr>@endforeach</tbody></table></div>
        @endif
        <p class="px-4 py-3 text-sm text-encre-2 border-t border-filet">Les {{ config('inps.sauvegarde.retention') }} dernières sauvegardes sont gardées. Copiez-en une régulièrement hors du serveur.
            @unless (config('inps.sauvegarde.mot_de_passe'))<strong>Ces fichiers ne sont pas protégés par un mot de passe : conservez-les en lieu sûr.</strong>@endunless
            Pour <strong>restaurer</strong> une sauvegarde, faites appel à l'informaticien (procédure : docs/SAUVEGARDE_RESTAURATION.md).</p>
    </x-carte>

    <details class="carte">
        <summary class="cursor-pointer px-4 py-3 font-semibold text-sm">Informations techniques (pour l'informaticien)</summary>
        <div class="px-4 pb-4 space-y-5">
            <dl class="dl-grille">@foreach ($technique as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach</dl>
            <p class="aide">Contrôle complet en ligne de commande : <code>php artisan inps:sante</code></p>
            <div>
                <h3 class="font-semibold text-sm mb-2">Derniers envois d'e-mails</h3>
                @if ($emails->isEmpty())<p class="text-sm text-encre-3">Aucun envoi tenté (e-mails désactivés ou aucune notification).</p>@else
                    <table><thead><tr><th>Date</th><th>Sujet</th><th>Résultat</th><th>Détail</th></tr></thead>
                        <tbody>@foreach ($emails as $e)<tr><td>{{ date_fr($e->created_at, true) }}</td><td>{{ $e->sujet }}</td><td>{{ ['envoye' => 'Transmis au serveur SMTP', 'echec' => 'Échec', 'non_remis' => 'Non remis (pas de SMTP)'][$e->statut] ?? $e->statut }}</td><td class="text-xs">{{ $e->erreur }}</td></tr>@endforeach</tbody></table>
                @endif
            </div>
        </div>
    </details>
</x-layout>
