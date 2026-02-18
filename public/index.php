<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// MadelineProto WebRunner: IPC script must be within document root.
// Point MADELINE_PHP to our wrapper in public/ so the "runPath within root" check passes.
if (!defined('MADELINE_PHP')) {
    define('MADELINE_PHP', __DIR__ . DIRECTORY_SEPARATOR . 'madeline-ipc.php');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
