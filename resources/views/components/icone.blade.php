{{--
    Icônes au trait (24×24, 1,75 px), dessinées d'après le jeu Lucide (licence ISC).
    Usage : <x-icone nom="besoins" class="h-5 w-5" />  — décoratives par défaut (aria-hidden).
--}}
@props(['nom', 'titre' => null])
@php
    $traces = [
        'tableau' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'espace' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'besoins' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h6"/>',
        'actions' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/><path d="M22 10v6"/>',
        'sessions' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><circle cx="12" cy="16" r="2.5"/>',
        'evaluations' => '<path d="M9 11l2 2 4-4"/><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 17h8"/>',
        'diplome' => '<path d="M4 5h13a3 3 0 0 1 0 6H8"/><path d="M4 5a3 3 0 0 0 0 6h4v8l2.5-1.5L13 19v-8"/><path d="M17 11v6a2 2 0 0 1-2 2h-2"/>',
        'attestations' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 13.8-1.5 8.2 5-3 5 3-1.5-8.2"/>',
        'personnel' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'importations' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
        'organismes' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v8h4M18 9h2a2 2 0 0 1 2 2v11h-4M10 6h4M10 10h4M10 14h4M10 18h4"/>',
        'formateurs' => '<path d="M2 3h20M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3M7 21l5-5 5 5"/>',
        'rapports' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
        'parametres' => '<path d="M20 7h-9M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'referentiels' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'utilisateurs' => '<circle cx="10" cy="8" r="4"/><path d="M2 21a8 8 0 0 1 11.3-7.3"/><circle cx="18" cy="17" r="3"/><path d="M18 12v2M18 20v2M22 17h-2M16 17h-2"/>',
        'roles' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1Z"/><path d="m9 12 2 2 4-4"/>',
        'audit' => '<path d="M15 12h-5M15 8h-5M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/>',
        'corbeille' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/>',
        'systeme' => '<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><path d="M6 6h.01M6 18h.01"/>',
        'loupe' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'filtre' => '<path d="M3 5h18l-7 8v6l-4 2v-8Z"/>',
        'exporter' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',
        'tendance' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'groupe' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>',
        'cloche' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'deconnexion' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'cle' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'replier' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M16 15l-3-3 3-3"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'oeil' => '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>',
        'oeil-barre' => '<path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.58 3.18 9.94 7a10.7 10.7 0 0 1-1.6 3.02M14.08 14.16a3 3 0 0 1-4.24-4.24M17.48 17.5A10.7 10.7 0 0 1 12 19c-4.64 0-8.58-3.18-9.94-7a10.8 10.8 0 0 1 4.46-5.5M2 2l20 20"/>',
        'succes' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'attention' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
        'erreur' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'vide' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11"/>',
        'cadenas' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'valide' => '<path d="M20 6 9 17l-5-5"/>',
        'fermer' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'calendrier' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'horloge' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    ];
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
     {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }} @if ($titre) role="img" aria-label="{{ $titre }}" @else aria-hidden="true" focusable="false" @endif>{!! $traces[$nom] ?? $traces['info'] !!}</svg>
