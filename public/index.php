<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Ensure .env exists before Laravel boots (for fresh installs)
if (!file_exists(__DIR__.'/../.env')) {
    copy(__DIR__.'/../.env.example', __DIR__.'/../.env');
}
if (!file_exists(__DIR__.'/../database/database.sqlite')) {
    @touch(__DIR__.'/../database/database.sqlite');
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
