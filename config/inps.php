<?php

/*
| Configuration technique propre à l'application (les règles métier sont dans la table
| « parametres », modifiables depuis l'écran Paramétrage).
*/
return [
    // Force HTTPS (redirections, URL générées, cookies sécurisés) — obligatoire en production.
    'forcer_https' => env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    // Mot de passe des comptes de démonstration (php artisan db:seed --class=DemoSeeder ; jamais en production)
    'demo_mot_de_passe' => env('DEMO_PASSWORD'),

    'sauvegarde' => [
        // « ?: » et non une valeur par défaut d'env() : une ligne « BACKUP_PATH= » laissée vide dans .env
        // renvoie une chaîne vide, et le dossier vide provoquait « mkdir(): Invalid path » (constaté le 28/10/2026)
        'repertoire' => env('BACKUP_PATH') ?: storage_path('app/sauvegardes'),
        'mot_de_passe' => env('BACKUP_PASSWORD'), // chiffrement AES-256 de l'archive si défini
        'retention' => (int) (env('BACKUP_RETENTION') ?: 14),
        'mysqldump' => env('MYSQLDUMP_PATH') ?: 'mysqldump',
        'mysql' => env('MYSQL_CLIENT_PATH') ?: 'mysql',
    ],

    'securite' => [
        'tentatives_connexion' => (int) (env('LOGIN_MAX_ATTEMPTS') ?: 5), // par minute et par couple e-mail/IP
        'mot_de_passe_longueur' => max(8, (int) (env('PASSWORD_MIN_LENGTH') ?: 12)),
        'mot_de_passe_compromis' => (bool) env('PASSWORD_CHECK_PWNED', false), // nécessite un accès Internet
        'inactivite_minutes' => (int) (env('SESSION_LIFETIME') ?: 30),
    ],
];
