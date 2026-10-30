<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Application en maintenance (php artisan down) : page d'attente
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Chargement automatique des classes (Composer)
require __DIR__.'/../vendor/autoload.php';

// Démarrage de Laravel et traitement de la requête
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
