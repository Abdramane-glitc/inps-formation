<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentification par défaut
    |--------------------------------------------------------------------------
    | Garde et gestionnaire de réinitialisation des mots de passe utilisés par défaut.
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gardes d'authentification
    |--------------------------------------------------------------------------
    | Manière de reconnaître l'utilisateur connecté : ici par la session du navigateur.
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Source des comptes
    |--------------------------------------------------------------------------
    | Les comptes sont lus dans la table users par le modèle App\Models\User.
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Réinitialisation des mots de passe
    |--------------------------------------------------------------------------
    | Table des jetons, durée de validité d'un lien (minutes) et délai entre deux demandes (secondes).
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmation du mot de passe
    |--------------------------------------------------------------------------
    | Durée (secondes) pendant laquelle une confirmation du mot de passe reste valable.
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
