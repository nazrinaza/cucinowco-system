<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appRoot = dirname(__DIR__, 2).'/cucinowco-system';

if (! is_file($appRoot.'/vendor/autoload.php') || ! is_file($appRoot.'/bootstrap/app.php')) {
    http_response_code(503);
    exit('CuciNow is temporarily unavailable while the production release is being prepared.');
}

if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
