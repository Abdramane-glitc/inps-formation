<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Stockage des sessions
    |--------------------------------------------------------------------------
    | Où sont gardées les sessions des utilisateurs connectés : en base de données.
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Durée de session
    |--------------------------------------------------------------------------
    | Déconnexion après ce nombre de minutes d'inactivité (SESSION_LIFETIME).
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 30),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', true),

    /*
    |--------------------------------------------------------------------------
    | Chiffrement des sessions
    |--------------------------------------------------------------------------
    | Les données de session sont chiffrées avec APP_KEY.
    */

    'encrypt' => env('SESSION_ENCRYPT', true),

    /*
    |--------------------------------------------------------------------------
    | Dossier des sessions
    |--------------------------------------------------------------------------
    | Dossier utilisé si les sessions sont stockées dans des fichiers.
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Base des sessions
    |--------------------------------------------------------------------------
    | Connexion utilisée pour la table des sessions (vide = connexion par défaut).
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Table des sessions
    |--------------------------------------------------------------------------
    | Nom de la table qui contient les sessions.
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Cache des sessions
    |--------------------------------------------------------------------------
    | Mécanisme de cache utilisé si les sessions sont stockées dans le cache.
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Nettoyage des sessions
    |--------------------------------------------------------------------------
    | Probabilité de supprimer les sessions expirées à chaque requête.
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Nom du cookie de session
    |--------------------------------------------------------------------------
    | Nom du cookie qui identifie la session dans le navigateur.
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Chemin du cookie
    |--------------------------------------------------------------------------
    | Partie du site pour laquelle le cookie de session est envoyé.
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Domaine du cookie
    |--------------------------------------------------------------------------
    | Domaine pour lequel le cookie de session est valable.
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Cookie en HTTPS seulement
    |--------------------------------------------------------------------------
    | En production, le cookie n'est envoyé que sur une connexion chiffrée (HTTPS).
    */

    'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Cookie inaccessible au JavaScript
    |--------------------------------------------------------------------------
    | Empêche un script de lire le cookie de session (protection contre le vol de session).
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Cookie « same-site »
    |--------------------------------------------------------------------------
    | Limite l'envoi du cookie depuis d'autres sites (protection contre la falsification de requêtes).
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Cookies partitionnés
    |--------------------------------------------------------------------------
    | Option des navigateurs récents pour les cookies intersites (non utilisée).
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | Format des sessions
    |--------------------------------------------------------------------------
    | Format d'enregistrement des sessions : json (le plus sûr).
    */

    'serialization' => 'json',

];
