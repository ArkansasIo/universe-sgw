<?php

declare(strict_types=1);

namespace StellarDominion\Core;

use PDO;
use PDOException;

/**
 * PDO singleton for MySQL (mirrors server/db/index.ts pg Pool).
 *
 * Usage:
 *   $db = Database::connection();
 *   $db->query("SELECT 1");
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::get('database.host', '127.0.0.1');
        $port = (int) Config::get('database.port', 3306);
        $name = Config::get('database.name', 'stellar_dominion');
        $user = Config::get('database.user', 'root');
        $pass = Config::get('database.pass', '');
        $charset = Config::get('database.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    /** Run a callback inside a transaction. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Reset the singleton (used by tests). */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
