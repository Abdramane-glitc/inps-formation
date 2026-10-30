<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disque par défaut
    |--------------------------------------------------------------------------
    | Emplacement de stockage des fichiers utilisé par défaut.
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disques de stockage
    |--------------------------------------------------------------------------
    | « prive » contient les pièces jointes : jamais accessible directement par une adresse web.
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        // Disque privé des documents : hors racine web, jamais servi directement
        // (téléchargement exclusivement via DocumentController, après autorisation).
        'prive' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => true,
            'visibility' => 'private',
            'permissions' => ['file' => ['private' => 0640], 'dir' => ['private' => 0750]],
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Liens symboliques
    |--------------------------------------------------------------------------
    | Liens créés par php artisan storage:link.
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
