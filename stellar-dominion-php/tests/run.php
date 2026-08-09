<?php

declare(strict_types=1);

/**
 * Lightweight test runner — no external dependencies required.
 *
 * Usage:
 *   php tests/run.php
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

$config = require $base . '/app/Config/settings.php';
foreach ($config as $section => $values) {
    foreach ($values as $key => $value) {
        Config::set("{$section}.{$key}", $value);
    }
}

$tests = [];
$assertions = 0;
$failures = 0;

function test(string $name, callable $fn): void
{
    global $tests;
    $tests[] = ['name' => $name, 'fn' => $fn];
}

function assertTrue(bool $cond, string $message = 'Expected true'): void
{
    global $assertions, $failures;
    $assertions++;
    if (!$cond) {
        $failures++;
        echo "  FAIL: $message\n";
    }
}

function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
{
    global $assertions, $failures;
    $assertions++;
    if ($expected !== $actual) {
        $failures++;
        echo "  FAIL: $message\n    expected: " . var_export($expected, true) . "\n    actual:   " . var_export($actual, true) . "\n";
    }
}

function assertLessThan(mixed $bound, mixed $actual, string $message = ''): void
{
    global $assertions, $failures;
    $assertions++;
    if (!($actual < $bound)) {
        $failures++;
        echo "  FAIL: $message (expected < $bound, got $actual)\n";
    }
}

require __DIR__ . '/FormulaTest.php';
require __DIR__ . '/CombatEngineTest.php';

$passed = 0;
foreach ($tests as $t) {
    echo "• {$t['name']}\n";
    try {
        ($t['fn'])();
        $passed++;
    } catch (Throwable $e) {
        $failures++;
        echo "  ERROR: " . $e->getMessage() . "\n";
        echo "  " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

echo "\n";
echo "Tests: $passed/" . count($tests) . " passed, $failures assertions failed\n";
echo "Assertions: $assertions\n";
exit($failures > 0 ? 1 : 0);
