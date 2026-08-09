<?php

declare(strict_types=1);

/**
 * Application settings (environment / infra).
 *
 * Values can be overridden with real environment variables:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, APP_ENV, APP_DEBUG,
 *   APP_URL, SESSION_NAME, TICK_INTERVAL
 */

return [
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => (getenv('APP_DEBUG') ?: 'false') === 'true',
    'base_path' => dirname(__DIR__),

    'web' => [
        'url' => getenv('APP_URL') ?: 'http://localhost:8080',
    ],

    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'stellar_dominion',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name' => getenv('SESSION_NAME') ?: 'stellar_sid',
        'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 604800), // 7 days
        'secure' => (getenv('SESSION_SECURE') ?: 'false') === 'true',
    ],

    'tick' => [
        'interval_ms' => (int) (getenv('TICK_INTERVAL') ?: 60000), // 1 minute
    ],
];
