<?php

declare(strict_types=1);

namespace StellarDominion\Models;

use StellarDominion\Core\Database;

/**
 * Thin data models shared by the API layer.
 *
 * Rather than a full ORM, these small models wrap the PDO queries used by
 * controllers — mirroring the original drizzle query helpers.
 */
final class Fleet
{
    public static function find(string $id): ?array
    {
        $row = Database::connection()->query('SELECT * FROM fleets WHERE id = ?', [$id])->fetch();
        return $row !== false ? self::hydrate($row) : null;
    }

    public static function forPlayer(string $playerId): array
    {
        $rows = Database::connection()
            ->query('SELECT * FROM fleets WHERE owner_id = ? ORDER BY created_at DESC', [$playerId])
            ->fetchAll();
        return array_map([self::class, 'hydrate'], $rows);
    }

    private static function hydrate(array $row): array
    {
        foreach (['ships', 'resources'] as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $row[$col] = json_decode($row[$col], true);
            }
        }
        return $row;
    }
}

/**
 * Mission model.
 */
final class Mission
{
    public static function find(string $id): ?array
    {
        $row = Database::connection()->query('SELECT * FROM missions WHERE id = ?', [$id])->fetch();
        return $row !== false ? self::hydrate($row) : null;
    }

    public static function forPlayer(string $playerId): array
    {
        $rows = Database::connection()
            ->query('SELECT * FROM missions WHERE user_id = ? ORDER BY created_at DESC', [$playerId])
            ->fetchAll();
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function active(string $playerId): array
    {
        $rows = Database::connection()
            ->query("SELECT * FROM missions WHERE user_id = ? AND status = 'outbound' ORDER BY arrival_time ASC", [$playerId])
            ->fetchAll();
        return array_map([self::class, 'hydrate'], $rows);
    }

    private static function hydrate(array $row): array
    {
        foreach (['units', 'cargo', 'result'] as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $row[$col] = json_decode($row[$col], true);
            }
        }
        return $row;
    }
}

/**
 * Battle model.
 */
final class Battle
{
    public static function find(string $id): ?array
    {
        $row = Database::connection()->query('SELECT * FROM battles WHERE id = ?', [$id])->fetch();
        return $row !== false ? self::hydrate($row) : null;
    }

    public static function forPlayer(string $playerId): array
    {
        $rows = Database::connection()
            ->query('SELECT * FROM battles WHERE attacker_id = ? OR defender_id = ? ORDER BY created_at DESC LIMIT 50', [$playerId, $playerId])
            ->fetchAll();
        return array_map([self::class, 'hydrate'], $rows);
    }

    private static function hydrate(array $row): array
    {
        foreach (['attacker_fleet', 'defender_fleet', 'attacker_losses', 'defender_losses', 'loot', 'debris', 'battle_log'] as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $row[$col] = json_decode($row[$col], true);
            }
        }
        return $row;
    }
}
