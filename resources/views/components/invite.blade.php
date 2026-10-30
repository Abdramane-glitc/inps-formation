{{--
    Gabarit des pages publiques (connexion, activation, mot de passe oublié, réinitialisation) — version 2.5.2.
    Fond bleu nuit de l'INPS avec une trame de points reliés ; à gauche le logo, le titre et le formulaire ;
    à droite (grand écran) la photographie officielle du siège, présentée dans une bande oblique et un cercle.
    Photographie : Paramètres › Identité institutionnelle, ou public/images/institution/siege.jpg.
    Sans photographie : seules les formes décoratives sont affichées (rendu de repli).
--}}
@props(['titre' => null])
@php
    $couleur = \App\Models\Parametre::valeur('institution.couleur_principale', '#003B73');
    $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $couleur) ? $couleur : '#003B73';
    $sigle = \App\Models\Parametre::valeur('institution.sigle', 'INPS');
    $photo = \App\Support\Identite::url('photo');
    $logo = \App\Support\Identite::url('logo');
    $direction = \App\Models\Parametre::valeur('institution.direction_rh', 'Direction des Ressources humaines');
@endphp
<!DOCTYPE html>
<html lang="fr" style="--couleur-principale: {{ $couleur }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#021B36">
    <title>{{ $titre ? $titre.' — ' : '' }}INPS Formation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="invite-corps">
<div class="invite">
    {{-- Trame décorative : points reliés (évoque le réseau des agents formés) --}}
    <svg class="invite-trame" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid slice" viewBox="0 0 1440 900">
        <g stroke="currentColor" stroke-width="1" fill="none" opacity=".35">
            <path d="M80 140 320 260 560 120 760 300 1010 170 1260 260 1400 90"/>
            <path d="M40 620 260 520 470 700 700 560 920 760 1180 640 1420 800"/>
            <path d="M320 260 260 520M560 120 700 560M760 300 920 760M1010 170 1180 640"/>
        </g>
        <g fill="currentColor">
            <circle cx="80" cy="140" r="3"/><circle cx="320" cy="260" r="5"/><circle cx="560" cy="120" r="3"/><circle cx="760" cy="300" r="6"/>
            <circle cx="1010" cy="170" r="4"/><circle cx="1260" cy="260" r="3"/><circle cx="260" cy="520" r="4"/><circle cx="470" cy="700" r="3"/>
            <circle cx="700" cy="560" r="5"/><circle cx="920" cy="760" r="3"/><circle cx="1180" cy="640" r="4"/>
        </g>
    </svg>

    <section class="connexion-visuel {{ $photo ? '' : 'sans-photo' }}" aria-label="Institut National de Prévoyance Sociale du Mali">
        @if ($photo)
            <div class="visuel-bande"><img class="photo" src="{{ $photo }}" alt="Siège de l'Institut National de Prévoyance Sociale du Mali"></div>
            <div class="visuel-cercle"><img src="{{ $photo }}" alt=""></div>
        @else
            <div class="visuel-bande"></div>
            <div class="visuel-cercle"></div>
        @endif
        <div class="visuel-trait" aria-hidden="true"></div>
    </section>

    <main class="invite-contenu" id="contenu">
        <div class="invite-colonne">
            <div class="invite-marque">
                @if ($logo)
                    <span class="invite-logo"><img src="{{ $logo }}" alt="Logo de l'{{ $sigle }}"></span>
                @else
                    <span class="invite-logo invite-logo-texte" aria-hidden="true">{{ mb_substr($sigle, 0, 4) }}</span>
                @endif
                <span class="invite-nom"><strong>INPS Formation</strong><span>Institut National de Prévoyance Sociale du Mali</span></span>
            </div>

            {{ $slot }}

            <div class="invite-pied">
                <p class="flex gap-2"><x-icone nom="cadenas" class="h-4 w-4 flex-none mt-0.5" />Accès réservé au personnel habilité de l'INPS. Les connexions sont enregistrées dans le journal de sécurité de l'application.</p>
                <p class="mt-2">{{ $direction }} — gestion des demandes et des formations du personnel.</p>
                @unless (app()->environment('production'))
                    <p class="mt-3"><span class="statut bg-alerte-fond text-alerte">Environnement de test — données fictives uniquement</span></p>
                @endunless
            </div>
        </div>
    </main>
</div>
</body>
</html>
