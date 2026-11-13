<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appRoot = null;
foreach ([
    // Primary domains commonly point directly to /home/USER/public_html.
    dirname(__DIR__).'/cucinowco-system',
    // Add-on domains commonly use /home/USER/public_html/DOMAIN.
    dirname(__DIR__, 2).'/cucinowco-system',
] as $candidate) {
    if (is_file($candidate.'/bootstrap/app.php')) {
        $appRoot = $candidate;
        break;
    }
}

if (! is_string($appRoot) || ! is_file($appRoot.'/vendor/autoload.php')) {
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
