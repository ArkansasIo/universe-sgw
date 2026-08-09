<?php

declare(strict_types=1);

/**
 * Seed the database with catalog data (buildings, technologies, ships,
 * defenses, realms, enemy races) and a starter admin account.
 *
 * Usage:
 *   php database/seed.php
 *   php database/seed.php --with-demo     also create a demo player
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
use StellarDominion\Core\Database;
use StellarDominion\Core\Logger;

$config = require $base . '/app/Config/settings.php';

foreach ($config as $section => $values) {
    foreach ($values as $key => $value) {
        Config::set("{$section}.{$key}", $value);
    }
}

$db = Database::connection();
$seedDataDir = $base . '/database/seeds';

$files = array_filter(scandir($seedDataDir) ?: [], static fn (string $f): bool => str_ends_with($f, '.sql') || str_ends_with($f, '.php'));
sort($files);

foreach ($files as $file) {
    echo "Seeding: $file\n";
    $path = $seedDataDir . '/' . $file;
    if (str_ends_with($file, '.sql')) {
        $sql = file_get_contents($path);
        if ($sql === false) {
            Logger::error("Could not read seed file $file");
            continue;
        }
        $db->exec($sql);
    } else {
        // PHP seed files use $base and Database from the outer scope.
        require $path;
    }
}

// Admin account (only if not present)
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@stellar.test';
$adminPass = getenv('ADMIN_PASS') ?: 'changeme123';
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$adminEmail]);
if ($stmt->fetch() === false) {
    $adminId = \StellarDominion\Core\UUID::v4();
    $stmt = $db->prepare(
        'INSERT INTO users (id, username, password_hash, email, is_admin, first_name, last_name)
         VALUES (?, ?, ?, ?, 1, ?, ?)'
    );
    $stmt->execute([
        $adminId,
        'Admin',
        password_hash($adminPass, PASSWORD_BCRYPT),
        $adminEmail,
        'Stellar',
        'Admin',
    ]);
    echo "Created admin user ($adminEmail).\n";
} else {
    echo "Admin user already exists, skipping.\n";
}

echo "Seed complete.\n";
