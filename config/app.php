<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nom de l'application
    |--------------------------------------------------------------------------
    | Nom affiché dans les notifications et les e-mails (APP_NAME).
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Environnement
    |--------------------------------------------------------------------------
    | local, testing ou production (APP_ENV). En production, la démonstration est interdite.
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Mode débogage
    |--------------------------------------------------------------------------
    | APP_DEBUG=true affiche le détail des erreurs : toujours false en production.
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Adresse de l'application
    |--------------------------------------------------------------------------
    | Adresse utilisée pour les liens des e-mails et des commandes Artisan (APP_URL).
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Fuseau horaire
    |--------------------------------------------------------------------------
    | Fuseau utilisé pour les dates et heures (le Mali est à l'heure UTC).
    */

    'timezone' => env('APP_TIMEZONE', 'Africa/Bamako'),

    /*
    |--------------------------------------------------------------------------
    | Langue
    |--------------------------------------------------------------------------
    | Langue des messages de l'application et des règles de validation.
    */

    'locale' => env('APP_LOCALE', 'fr'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'fr'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'fr_FR'),

    /*
    |--------------------------------------------------------------------------
    | Clé de chiffrement
    |--------------------------------------------------------------------------
    | Clé APP_KEY (32 caractères aléatoires) : chiffre les sessions et les codes d'activation. Ne jamais la changer après la mise en service, ni la publier.
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode maintenance
    |--------------------------------------------------------------------------
    | Où est enregistré l'état « en maintenance » (php artisan down) : file, cache ou array.
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
