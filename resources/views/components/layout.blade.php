@props(['titre' => null])
@php
    $u = auth()->user();
    $p = fn (...$c) => $u->aUneDesPermissions(...$c);
    $couleur = \App\Models\Parametre::valeur('institution.couleur_principale', '#003B73');
    $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $couleur) ? $couleur : '#003B73';
    $sigle = \App\Models\Parametre::valeur('institution.sigle', 'INPS');
    $nomInstitution = \App\Models\Parametre::valeur('institution.nom', 'Institut National de Prévoyance Sociale');
    $nonLues = $u->unreadNotifications()->count();
    // Pastille du menu « Demandes » : demandes envoyées en attente de décision (RH seulement)
    $aDecider = $u->aPermission('besoins.instruire') ? \App\Models\BesoinFormation::where('statut', \App\Enums\StatutBesoin::Soumise->value)->count() : 0;
    $compteurs = ['besoins.index' => $aDecider];
    $logo = \App\Support\Identite::url('logo');
    // [libellé, route, motif de route active, autorisé, icône]
    $menu = [
        'Pilotage' => [
            ['Tableau de bord', 'tableau-bord', 'tableau-bord', $p('tableau_bord.voir'), 'tableau'],
            ['Mon espace', 'espace.index', 'espace.*', $p('espace.personnel'), 'espace'],
        ],
        'Gestion des formations' => [
            ['Demandes', 'besoins.index', 'besoins.*', $p('besoins.voir_tous', 'besoins.voir_service'), 'besoins'],
            ['Plan annuel', 'plan.index', 'plan.*', $p('actions.voir'), 'calendrier'],
            ['Formations', 'formations.index', 'formations.*|sessions.*|participants.*|evaluations.chaud*|rapport-realisation.*', $p('actions.voir'), 'actions'],
            ['Évaluations', 'evaluations.index', 'evaluations.index|evaluations.froid*', $p('evaluations.gerer', 'evaluations.froid'), 'evaluations'],
            ['Attestations', 'attestations.index', 'attestations.*', $p('attestations.emettre'), 'attestations'],
            ['Formations diplômantes', 'diplomantes.index', 'diplomantes.*', $p('diplomantes.gerer'), 'diplome'],
        ],
        'Référentiels' => [
            ['Personnel', 'agents.index', 'agents.*', $p('agents.voir_tous', 'agents.voir_service'), 'personnel'],
            ['Importations', 'imports.index', 'imports.*', $p('agents.importer'), 'importations'],
            ['Organismes', 'organismes.index', 'organismes.*', $p('organismes.voir'), 'organismes'],
            ['Formateurs', 'formateurs.index', 'formateurs.*', $p('organismes.voir'), 'formateurs'],
        ],
        'Suivi' => [
            ['Rapports', 'rapports.index', 'rapports.*', $p('rapports.voir'), 'rapports'],
        ],
        'Administration' => [
            ['Paramètres', 'admin.parametres.index', 'admin.parametres.*', $p('parametres.gerer'), 'parametres'],
            ['Référentiels', ['admin.referentiels.index', 'directions'], 'admin.referentiels.*', $p('parametres.gerer'), 'referentiels'],
            ['Utilisateurs', 'admin.utilisateurs.index', 'admin.utilisateurs.*', $p('utilisateurs.gerer'), 'utilisateurs'],
            ['Profils et droits', 'admin.roles.index', 'admin.roles.*', $p('roles.gerer'), 'roles'],
            ['Journal d\'audit', 'admin.audit.index', 'admin.audit.*', $p('audit.voir'), 'audit'],
            ['Sauvegardes', 'admin.systeme.index', 'admin.systeme.*', $p('systeme.superviser'), 'systeme'],
            ['Corbeille', 'corbeille.index', 'corbeille.*', $p('actions.gerer'), 'corbeille'],
        ],
    ];
    // Fil d'Ariane : rubrique active déduite du menu
    $rubrique = null;
    foreach ($menu as $section => $liens) {
        foreach ($liens as $l) {
            if ($l[3] && request()->routeIs(...explode('|', $l[2]))) {
                $rubrique = [$section, $l[0], is_array($l[1]) ? route($l[1][0], $l[1][1]) : route($l[1])];
            }
        }
    }
    $initiales = collect(preg_split('/\s+/', trim($u->name)))->filter(fn ($m) => preg_match('/^\p{L}/u', $m))->take(2)->map(fn ($m) => mb_strtoupper(mb_substr($m, 0, 1)))->implode('');
    $roles = $u->roles->pluck('nom')->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="fr" style="--couleur-principale: {{ $couleur }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#003B73">
    <title>{{ $titre ? $titre.' — ' : '' }}INPS Formation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] bg-white px-4 py-2 rounded border border-filet">Aller au contenu</a>
<div class="coquille">
    <aside id="menu-lateral" class="barre-laterale no-print" aria-label="Menu principal">
        <div class="flex items-center gap-3 px-4 h-16 border-b border-filet shrink-0">
            @if ($logo)
                <img src="{{ $logo }}" alt="Logo de l'{{ $sigle }}" class="h-11 w-14 shrink-0 object-contain">
            @else
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-inps text-[11px] font-bold tracking-wide text-white" aria-hidden="true">{{ mb_substr($sigle, 0, 4) }}</span>
            @endif
            <span class="marque-texte leading-tight min-w-0">
                <span class="block font-bold text-encre text-[15px]">{{ $sigle }} Formation</span>
                <span class="block text-[11.5px] text-encre-3 truncate" title="{{ $nomInstitution }}">{{ $nomInstitution }}</span>
            </span>
        </div>
        <nav aria-label="Rubriques">
            @foreach ($menu as $section => $liens)
                @php $visibles = array_filter($liens, fn ($l) => $l[3]); @endphp
                @if ($visibles)
                    <div class="titre-menu">{{ $section }}</div>
                    <div class="separateur-menu" aria-hidden="true"></div>
                    @foreach ($visibles as [$libelle, $route, $motif, , $icone])
                        @php $url = is_array($route) ? route($route[0], $route[1]) : route($route); $actif = request()->routeIs(...explode('|', $motif)); @endphp
                        <a href="{{ $url }}" class="lien-menu {{ $actif ? 'actif' : '' }}" title="{{ $libelle }}" @if ($actif) aria-current="page" @endif>
                            <x-icone :nom="$icone" class="h-[18px] w-[18px]" /><span class="libelle-menu">{{ $libelle }}</span>
                            @if (! is_array($route) && ($compteurs[$route] ?? 0))<span class="compteur-menu" aria-label="{{ $compteurs[$route] }} en attente">{{ $compteurs[$route] }}</span>@endif
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
        <div class="hidden lg:block border-t border-filet p-2">
            <button type="button" class="lien-menu w-full bouton-replier" data-menu-reduire aria-label="Réduire ou déployer le menu" title="Réduire le menu">
                <x-icone nom="replier" class="h-[18px] w-[18px] transition-transform" /><span class="libelle-menu">Réduire le menu</span>
            </button>
        </div>
    </aside>
    <button type="button" class="voile-menu" data-menu-fermer aria-label="Fermer le menu"></button>

    <div class="zone-principale">
        <header class="barre-haute no-print sticky top-0 z-30">
            <div class="flex items-center gap-3 h-16 px-4 lg:px-6">
                <button type="button" class="bouton-menu-mobile btn btn-secondaire btn-petit !px-2" data-menu-ouvrir aria-controls="menu-lateral" aria-label="Ouvrir le menu">
                    <x-icone nom="menu" />
                </button>
                {{-- Recherche générale : Entrée ou la loupe ouvre la page de résultats ; suggestions pendant la frappe (app.js, data-suggestions) --}}
                <form method="GET" action="{{ route('recherche') }}" class="recherche-globale" role="search" data-sans-chargement>
                    <button type="submit" class="recherche-loupe" aria-label="Lancer la recherche" title="Rechercher"><x-icone nom="loupe" class="h-[17px] w-[17px]" /></button>
                    <label for="recherche-globale" class="sr-only">Rechercher</label>
                    <input id="recherche-globale" type="search" name="q" value="{{ request()->routeIs('recherche') ? request('q') : '' }}" placeholder="Rechercher un agent, une formation…" autocomplete="off" maxlength="100"
                           data-suggestions="{{ route('recherche') }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="recherche-suggestions">
                    <kbd aria-hidden="true">/</kbd>
                    <div id="recherche-suggestions" class="recherche-suggestions" role="listbox" aria-label="Suggestions" hidden></div>
                </form>
                <div class="flex-1"></div>
                @unless (app()->environment('production'))
                    <span class="hidden md:inline-flex statut bg-alerte-fond text-alerte" title="Cette instance n'est pas l'environnement de production">{{ app()->environment() === 'local' ? 'Environnement de test' : 'Environnement : '.app()->environment() }}</span>
                @endunless
                <a href="{{ route('notifications.index') }}" class="bouton-icone"
                   aria-label="Notifications{{ $nonLues ? ' : '.$nonLues.' non lue(s)' : '' }}">
                    <x-icone nom="cloche" />
                    @if ($nonLues)<span class="absolute top-1.5 right-1.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-erreur text-white text-[10.5px] font-bold grid place-items-center" aria-hidden="true">{{ $nonLues > 99 ? '99+' : $nonLues }}</span>@endif
                </a>
                <details class="menu-compte relative">
                    <summary class="flex items-center gap-2.5 rounded-lg py-1.5 pl-1.5 pr-2 hover:bg-fond-2" aria-label="Menu du compte">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-inps text-white text-xs font-bold" aria-hidden="true">{{ $initiales }}</span>
                        <span class="hidden md:block text-left leading-tight max-w-[14rem]">
                            <span class="block text-sm font-semibold text-encre truncate">{{ $u->name }}</span>
                            <span class="block text-xs text-encre-3 truncate">{{ $roles ?: 'Aucun rôle' }}</span>
                        </span>
                        <x-icone nom="chevron" class="h-4 w-4 text-encre-3" />
                    </summary>
                    <div class="menu-deroulant">
                        <div class="px-3 py-2 border-b border-filet mb-1">
                            <div class="text-sm font-semibold text-encre">{{ $u->name }}</div>
                            <div class="text-xs text-encre-3 break-all">{{ $u->identifiant }}</div>
                        </div>
                        @if ($u->aPermission('espace.personnel'))<a href="{{ route('espace.index') }}"><x-icone nom="espace" class="h-4 w-4 text-encre-3" /> Mon espace</a>@endif
                        <a href="{{ route('mot-de-passe.edit') }}"><x-icone nom="cle" class="h-4 w-4 text-encre-3" /> Changer mon mot de passe</a>
                        <form method="POST" action="{{ route('logout') }}" data-sans-chargement>
                            @csrf
                            <button type="submit"><x-icone nom="deconnexion" class="h-4 w-4 text-encre-3" /> Se déconnecter</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main id="contenu" class="flex-1 w-full max-w-[1440px] px-4 py-6 lg:px-8 lg:py-7">
            @if ($rubrique && $titre && $titre !== $rubrique[1] && url()->current() !== $rubrique[2])
                <nav class="ariane no-print" aria-label="Fil d'Ariane">
                    <span class="hidden sm:inline">{{ $rubrique[0] }}</span>
                    <x-icone nom="chevron" class="hidden sm:inline h-3.5 w-3.5 -rotate-90 text-filet-fort" />
                    <a href="{{ $rubrique[2] }}">{{ $rubrique[1] }}</a>
                    <x-icone nom="chevron" class="h-3.5 w-3.5 -rotate-90 text-filet-fort" />
                    <span>{{ $titre }}</span>
                </nav>
            @endif
            <x-messages />
            {{ $slot }}
        </main>
        <footer class="no-print px-4 lg:px-8 py-4 text-xs text-encre-3 border-t border-filet bg-white">
            {{ $nomInstitution }} — {{ \App\Models\Parametre::valeur('institution.direction_rh', 'Direction des Ressources humaines') }}
        </footer>
    </div>
</div>
</body>
</html>
