<?php

declare(strict_types=1);

/**
 * Run database migrations.
 *
 * Usage:
 *   php database/migrate.php            apply migrations in order
 *   php database/migrate.php --fresh    drop all tables then apply
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

use StellarDominion\Core\Database;

$config = require $base . '/app/Config/settings.php';

$host = getenv('DB_HOST') ?: ($config['database']['host'] ?? '127.0.0.1');
$port = getenv('DB_PORT') ?: ($config['database']['port'] ?? '3306');
$name = getenv('DB_NAME') ?: ($config['database']['name'] ?? 'stellar_dominion');
$user = getenv('DB_USER') ?: ($config['database']['user'] ?? 'root');
$pass = getenv('DB_PASS') ?: ($config['database']['password'] ?? '');

$fresh = in_array('--fresh', $argv, true);

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $name));
    $pdo->exec(sprintf('USE `%s`', $name));
} catch (PDOException $e) {
    fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if ($fresh) {
    echo "Dropping all tables...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$schema = file_get_contents($base . '/database/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Could not read database/schema.sql\n");
    exit(1);
}

echo "Applying schema...\n";
$pdo->exec($schema);
echo "Schema applied successfully.\n";
