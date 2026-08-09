<?php

declare(strict_types=1);

/**
 * Game tick runner — replaces the original setInterval cron jobs.
 *
 * In production, schedule this via cron / Task Scheduler:
 *
 *   * * * * *  php cron/tick.php
 *
 * Each invocation runs the pending game jobs (resource production, turn
 * generation, construction, research, missions, expeditions, bank interest).
 *
 * Usage:
 *   php cron/tick.php             run all jobs
 *   php cron/tick.php resource    run a single job
 */

$base = dirname(__DIR__);

if (is_file($base . '/vendor/autoload.php')) {
    require $base . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($base): void {
        $prefix = 'StellarDominion\\';
        if (str_starts_with($class, $prefix)) {
            $path = $base . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    });
}

use StellarDominion\Core\Config;
use StellarDominion\Engine\TickEngine;

// Bootstrap config
$config = require $base . '/app/Config/settings.php';
foreach ($config as $section => $values) {
    foreach ($values as $key => $value) {
        Config::set("{$section}.{$key}", $value);
    }
}

date_default_timezone_set('UTC');

$job = $argv[1] ?? null;

try {
    if ($job !== null) {
        $result = TickEngine::runJob($job);
        echo json_encode(['job' => $job, ...$result]) . PHP_EOL;
    } else {
        $result = TickEngine::run();
        echo json_encode(['tick' => 'complete', ...$result]) . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Tick failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
