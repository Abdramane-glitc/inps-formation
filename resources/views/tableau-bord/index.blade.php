{{--
    Tableau de bord (2.6.0) : cartes chiffrées comparées à l'année précédente, activité mois par mois,
    participants par mois, répartition par type, actions à traiter, formations par direction et évolution annuelle.
    Graphiques en HTML/CSS et SVG produits par le serveur : aucun script (politique CSP).
--}}
@php
    use App\Services\IndicateurService as I;
    $a = $k['actions'];
    $u = auth()->user();
    $vA = $u->aPermission('actions.voir');
    $vB = $u->aUneDesPermissions('besoins.voir_tous', 'besoins.voir_service');
    $al = $k['alertes'];
    $nbAlertes = $al['sessions_a_venir']->count() + $al['sessions_non_cloturees']->count() + ($al['besoins_a_completer'] ? 1 : 0) + ($al['attestations_a_emettre'] ? 1 : 0);
    $filtresActifs = collect($f)->except('annee')->filter()->count();
    $noms = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
    $moisCourant = (int) $f['annee'] === (int) now()->year ? (int) now()->month : null;

    // Formations par mois (barres empilées)
    $maxFormations = max(1, ...array_map(fn ($m) => $m['terminees'] + $m['programmees'] + $m['annulees'], $mois));
    $totalFormations = array_sum(array_map(fn ($m) => $m['terminees'] + $m['programmees'], $mois));
    // Participants par mois
    $maxParticipants = max(1, ...array_column($mois, 'participants'));
    $totalParticipants = array_sum(array_column($mois, 'participants'));

    // Répartition par type : demi-anneau (longueurs en % de l'arc)
    $types = [['Internes', $a['internes'], 'var(--color-graphe-1)'], ['Externes', $a['externes'], 'var(--color-graphe-3)'], ['Mixtes', $a['mixtes'], 'var(--color-graphe-5)']];
    $totalTypes = array_sum(array_column($types, 1));
    $arcs = [];
    $debut = 0;
    foreach ($types as [$lib, $n, $coul]) {
        if ($n > 0 && $totalTypes) {
            $long = 100 * $n / $totalTypes;
            $arcs[] = [$coul, max(0, $long - 1), $debut];
            $debut += $long;
        }
    }
    $maxAgentsDir = max(1, ...array_map(fn ($l) => (int) $l['agents'], $k['par_direction'] ?: [['agents' => 1]]));
@endphp
<x-layout titre="Tableau de bord">
    <x-entete titre="Tableau de bord" :sous-titre="'Année '.$f['annee'].' — chiffres calculés à partir des données enregistrées dans l\'application.'">
        <form method="GET" class="flex items-center gap-2" aria-label="Choisir l'année">
            @foreach (collect($f)->except('annee')->filter() as $cle => $val)<input type="hidden" name="{{ $cle }}" value="{{ $val }}">@endforeach
            <label for="f_annee" class="sr-only">Année</label>
            <select id="f_annee" name="annee" data-soumission-auto class="!min-h-[2.5rem] !w-auto !py-1.5 font-medium">@foreach ($annees as $an)<option value="{{ $an }}" @selected($f['annee'] == $an)>Année {{ $an }}</option>@endforeach</select>
            <noscript><button class="btn btn-secondaire">Afficher</button></noscript>
        </form>
        <details class="menu-compte relative">
            <summary class="btn btn-secondaire"><x-icone nom="filtre" class="h-4 w-4" />Filtrer @if ($filtresActifs)<span class="compteur-menu !ml-0">{{ $filtresActifs }}</span>@endif</summary>
            <form method="GET" class="menu-deroulant !w-[22rem] !p-4 space-y-3" aria-label="Filtrer les indicateurs">
                <input type="hidden" name="annee" value="{{ $f['annee'] }}">
                <div><label for="f_dir">Direction</label><select id="f_dir" name="direction_id"><option value="">Toutes les directions</option>@foreach ($directions as $d)<option value="{{ $d->id }}" @selected(($f['direction_id'] ?? null) == $d->id)>{{ $d->nom }}</option>@endforeach</select></div>
                <div><label for="f_srv">Service</label><select id="f_srv" name="service_id"><option value="">Tous les services</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(($f['service_id'] ?? null) == $s->id)>{{ $s->nom }}</option>@endforeach</select></div>
                <div><label for="f_dom">Domaine</label><select id="f_dom" name="domaine_id"><option value="">Tous les domaines</option>@foreach ($domaines as $d)<option value="{{ $d->id }}" @selected(($f['domaine_id'] ?? null) == $d->id)>{{ $d->nom }}</option>@endforeach</select></div>
                <div><label for="f_type">Type</label><select id="f_type" name="type"><option value="">Interne et externe</option><option value="interne" @selected(($f['type'] ?? null) === 'interne')>Interne</option><option value="externe" @selected(($f['type'] ?? null) === 'externe')>Externe</option><option value="mixte" @selected(($f['type'] ?? null) === 'mixte')>Mixte</option></select></div>
                <div class="flex gap-2 pt-1"><button class="btn btn-primaire">Appliquer</button>@if ($filtresActifs)<a href="{{ route('tableau-bord', ['annee' => $f['annee']]) }}" class="btn btn-secondaire">Effacer les filtres</a>@endif</div>
            </form>
        </details>
        @permission('rapports.voir')<a class="btn btn-secondaire" href="{{ route('rapports.index') }}"><x-icone nom="exporter" class="h-4 w-4" />Rapports et exports</a>@endpermission
    </x-entete>

    {{-- Cartes chiffrées --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-5" aria-label="Chiffres clés de l'année">
        <x-kpi icone="actions" libelle="Formations de l'année" :valeur="$resume['formations']" :evolution="I::variation($resume['formations'], $precedent['formations'])"
               :lien="$vA ? route('formations.index', ['annee' => $f['annee']]) : null"
               :detail="($a['planifiees'] + $a['en_cours']).' programmée(s), '.$a['realisees'].' terminée(s)'.($a['annulees'] ? ', '.$a['annulees'].' annulée(s)' : '')" />
        <x-kpi icone="groupe" libelle="Agents formés" :valeur="$k['agents_formes']" :evolution="I::variation($k['agents_formes'], $precedent['agents_formes'])"
               :detail="'agents distincts — '.$k['inscriptions_retenues'].' inscription(s) retenue(s)'" />
        <x-kpi icone="besoins" libelle="Demandes reçues" :valeur="$k['demandes_en_attente']"
               :lien="$vB && $k['demandes_en_attente'] ? route('besoins.index', ['statut' => 'soumise']) : null"
               :detail="'en attente de décision — demandes à organiser : '.$k['demandes_a_organiser']" />
        <x-kpi icone="succes" libelle="Participants ayant suivi" :valeur="pourcent($k['taux_achevement'])"
               :detail="'sur '.$k['base_taux'].' participant(s) des formations terminées'" />
    </section>

    {{-- Activité mois par mois --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] mb-5">
        <x-carte titre="Formations par mois" icone="calendrier">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-5">
                <p><span class="text-[1.6rem] font-semibold tracking-tight chiffre">{{ $totalFormations }}</span> <span class="text-sm text-encre-3">formation(s) programmée(s) ou terminée(s)</span></p>
                <div class="legende">
                    <span><i style="background: var(--color-graphe-1)"></i>Terminées</span>
                    <span><i style="background: var(--color-graphe-3)"></i>Programmées</span>
                    <span><i style="background: #D0D5DD"></i>Annulées</span>
                </div>
            </div>
            <div class="graphe-barres" role="img" aria-label="Nombre de formations par mois de début, par état">
                @foreach ($mois as $m)
                    @php $tot = $m['terminees'] + $m['programmees'] + $m['annulees']; @endphp
                    <div class="graphe-colonne" title="{{ $noms[$m['mois'] - 1] }} : {{ $m['terminees'] }} terminée(s), {{ $m['programmees'] }} programmée(s), {{ $m['annulees'] }} annulée(s)">
                        @if ($tot)<span class="graphe-valeur">{{ $tot }}</span>@endif
                        @if (! $tot)<div class="graphe-barre" style="height: 4px"></div>@endif
                        <div class="graphe-pile" style="height: {{ round(78 * $tot / $maxFormations) }}%">
                            @foreach ([['terminees', 'var(--color-graphe-1)'], ['programmees', 'var(--color-graphe-3)'], ['annulees', '#D0D5DD']] as [$cle, $coul])
                                @if ($m[$cle])<span style="flex: {{ $m[$cle] }} 1 0; background: {{ $coul }}"></span>@endif
                            @endforeach
                        </div>
                        <span class="graphe-etiquette {{ $m['mois'] === $moisCourant ? 'actuelle' : '' }}">{{ $noms[$m['mois'] - 1] }}</span>
                    </div>
                @endforeach
            </div>
        </x-carte>

        <x-carte titre="Participants par mois" icone="groupe">
            <div class="mb-5">
                <p><span class="text-[1.6rem] font-semibold tracking-tight chiffre">{{ $totalParticipants }}</span> <span class="text-sm text-encre-3">participation(s) retenue(s)</span></p>
            </div>
            <div class="graphe-barres" role="img" aria-label="Nombre de participants retenus par mois de début de formation">
                @foreach ($mois as $m)
                    @php $actuel = $m['mois'] === $moisCourant || (! $moisCourant && $m['participants'] === $maxParticipants && $m['participants'] > 0); @endphp
                    <div class="graphe-colonne" title="{{ $noms[$m['mois'] - 1] }} : {{ $m['participants'] }} participant(s)">
                        @if ($m['participants'] && ($actuel || $m['participants'] === $maxParticipants))<span class="graphe-valeur">{{ $m['participants'] }}</span>@endif
                        <div class="graphe-barre {{ $actuel ? 'actuelle' : '' }}" style="height: {{ max(4, round(78 * $m['participants'] / $maxParticipants)) }}%"></div>
                        <span class="graphe-etiquette {{ $m['mois'] === $moisCourant ? 'actuelle' : '' }}">{{ mb_substr($noms[$m['mois'] - 1], 0, 1) }}</span>
                    </div>
                @endforeach
            </div>
        </x-carte>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] mb-5">
        {{-- Répartition par type --}}
        <x-carte titre="Formations par type" icone="tendance">
            <div class="grid grid-cols-3 gap-2 mb-4">
                @foreach ($types as [$lib, $n, $coul])
                    <div class="border-l-2 pl-2.5" style="border-color: {{ $coul }}">
                        <div class="text-xs text-encre-3">{{ $lib }}</div>
                        <div class="text-lg font-semibold chiffre">{{ $n }}</div>
                    </div>
                @endforeach
            </div>
            @if ($totalTypes)
                <svg viewBox="0 0 200 112" class="mx-auto block w-full max-w-[18rem]" role="img" aria-label="{{ $a['internes'] }} interne(s), {{ $a['externes'] }} externe(s), {{ $a['mixtes'] }} mixte(s)">
                    <path d="M16 100 A84 84 0 0 1 184 100" fill="none" stroke="#EEF0F3" stroke-width="24" pathLength="100" />
                    @foreach ($arcs as [$coul, $long, $dep])
                        <path d="M16 100 A84 84 0 0 1 184 100" fill="none" stroke="{{ $coul }}" stroke-width="24" pathLength="100"
                              stroke-dasharray="{{ round($long, 2) }} 200" stroke-dashoffset="{{ -round($dep, 2) }}" />
                    @endforeach
                    <text x="100" y="92" text-anchor="middle" font-size="22" font-weight="700" fill="#101828">{{ $totalTypes }}</text>
                    <text x="100" y="108" text-anchor="middle" font-size="9" fill="#667085">formations</text>
                </svg>
            @else
                <p class="text-sm text-encre-3">Aucune formation pour ces critères.</p>
            @endif
        </x-carte>

        {{-- À traiter --}}
        <x-carte :titre="'À traiter'.($nbAlertes ? ' ('.$nbAlertes.')' : '')" icone="attention" sans-marge>
            @if ($nbAlertes === 0)
                <div class="flex items-center gap-3 px-5 py-6 text-sm text-encre-2"><x-icone nom="succes" class="h-5 w-5 text-ok" />Aucune formation ni aucun dossier n'appelle d'action pour le moment.</div>
            @else
                <ul class="divide-y divide-filet text-sm">
                    @foreach ($al['sessions_a_venir'] as $s)
                        <li class="flex gap-3 px-5 py-3.5">
                            <span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-inps-50 text-inps-2"><x-icone nom="calendrier" class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-encre">Formation du {{ date_fr($s->date_debut) }} — {{ $s->action->intitule }}</p>
                                <p class="mt-0.5 text-xs text-encre-3">{{ $s->code }}</p>
                            </div>
                            @if ($vA)<a href="{{ route('formations.show', $s->action_id) }}" class="btn btn-secondaire btn-petit self-center">Ouvrir</a>@endif
                        </li>
                    @endforeach
                    @foreach ($al['sessions_non_cloturees'] as $s)
                        <li class="flex gap-3 px-5 py-3.5">
                            <span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-alerte-fond text-alerte"><x-icone nom="attention" class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-encre">Formation passée le {{ date_fr($s->date_fin) }}, à marquer terminée — {{ $s->action->intitule }}</p>
                                <p class="mt-0.5 text-xs text-encre-3">{{ $s->code }} — cocher qui l'a suivie, puis « Formation terminée »</p>
                            </div>
                            @if ($vA)<a href="{{ route('formations.show', $s->action_id) }}" class="btn btn-secondaire btn-petit self-center">Ouvrir</a>@endif
                        </li>
                    @endforeach
                    @if ($al['besoins_a_completer'])
                        <li class="flex gap-3 px-5 py-3.5">
                            <span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-alerte-fond text-alerte"><x-icone nom="besoins" class="h-4 w-4" /></span>
                            <p class="flex-1 self-center font-medium text-encre">{{ $al['besoins_a_completer'] }} demande(s) renvoyée(s) aux services pour précisions</p>
                            @if ($vB)<a href="{{ route('besoins.index', ['statut' => 'a_completer']) }}" class="btn btn-secondaire btn-petit self-center">Voir</a>@endif
                        </li>
                    @endif
                    @if ($al['attestations_a_emettre'])
                        <li class="flex gap-3 px-5 py-3.5">
                            <span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-inps-50 text-inps-2"><x-icone nom="attestations" class="h-4 w-4" /></span>
                            <p class="flex-1 self-center font-medium text-encre">{{ $al['attestations_a_emettre'] }} formation(s) achevée(s) sans attestation émise</p>
                        </li>
                    @endif
                </ul>
            @endif
        </x-carte>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
        <x-carte titre="Formations par direction et service" icone="organismes" sans-marge>
            @if (empty($k['par_direction']))
                <x-vide message="Aucune participation enregistrée pour ces critères." />
            @else
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Direction</th><th>Service</th><th class="text-right">Formations</th><th>Agents</th></tr></thead>
                    <tbody>@foreach ($k['par_direction'] as $l)
                        <tr><td>{{ $l['direction'] }}</td><td>{{ $l['service'] }}</td><td class="text-right">{{ $l['actions'] }}</td>
                            <td class="min-w-[9rem]"><div class="flex items-center gap-2.5"><div class="jauge flex-1" aria-hidden="true"><span style="width: {{ round(100 * $l['agents'] / $maxAgentsDir) }}%"></span></div><span class="w-6 text-right chiffre">{{ $l['agents'] }}</span></div></td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </x-carte>
        <x-carte titre="Évolution d'une année à l'autre" icone="rapports" sans-marge>
            @if (empty($comparatif))
                <x-vide message="Aucune donnée annuelle." />
            @else
                <div class="overflow-x-auto"><table>
                    <thead><tr><th>Année</th><th class="text-right">Formations</th><th class="text-right">Réalisées</th><th class="text-right">Agents formés</th></tr></thead>
                    <tbody>@foreach ($comparatif as $c)<tr @if ($c['annee'] == $f['annee']) class="font-semibold" @endif><td>{{ $c['annee'] }}</td><td class="text-right">{{ $c['actions'] }}</td><td class="text-right">{{ $c['realisees'] }}</td><td class="text-right">{{ $c['agents_formes'] }}</td></tr>@endforeach</tbody>
                </table></div>
            @endif
        </x-carte>
    </div>
    <p class="aide mt-5">Les agents formés sont comptés une seule fois ; inscriptions et participations sont comptées séparément. Évolution : comparaison avec l'année précédente, mêmes filtres. Définitions détaillées : guide utilisateur, section « Tableau de bord ».</p>
</x-layout>
