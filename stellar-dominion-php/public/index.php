<?php

declare(strict_types=1);

/**
 * Front controller — single entry point for the web app.
 *
 *   php -S localhost:8080 -t public public/index.php
 *
 * or point your webserver's document root at public/ and rewrite all
 * requests to index.php.
 */

define('BASE_PATH', dirname(__DIR__));

// Autoload
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'StellarDominion\\';
        if (str_starts_with($class, $prefix)) {
            $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    });
}

use StellarDominion\Core\Config;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;

// Load configs
$config = require BASE_PATH . '/app/Config/settings.php';
foreach ($config as $section => $values) {
    foreach ($values as $key => $value) {
        Config::set("{$section}.{$key}", $value);
    }
}

date_default_timezone_set(Config::get('app.timezone', 'UTC') ?: 'UTC');

// CORS + preflight (development)
header('Access-Control-Allow-Origin: ' . Config::get('web.url', '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $request = Request::fromGlobals();

    $router = require BASE_PATH . '/app/Routes/web.php';
    $router->handle($request);
} catch (Throwable $e) {
    $debug = Config::get('debug', false) === true;
    $payload = [
        'success' => false,
        'error' => $debug ? $e->getMessage() : 'Internal server error',
    ];
    if ($debug) {
        $payload['trace'] = explode("\n", $e->getTraceAsString());
    }
    $status = $e instanceof StellarDominion\Core\HttpException ? $e->getStatusCode() : 500;
    Response::make($status)->json($payload);
}
