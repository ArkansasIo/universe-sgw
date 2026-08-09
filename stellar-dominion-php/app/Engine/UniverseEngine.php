<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Database;

/**
 * Universe generation: deterministic galaxy map.
 *
 * Port of routes-universe-seed.ts / universeSeedService.ts. Uses a seeded
 * PRNG so the same seed always produces the same galaxy layout.
 */
final class UniverseEngine
{
    /** Deterministic random float in [0,1) from a 32-bit seed. */
    private static function seededRandom(int &$seed): float
    {
        $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
        return $seed / 0x7FFFFFFF;
    }

    /**
     * Generate (and persist) star systems for a universe.
     *
     * @param int $galaxies    galaxy count
     * @param int $sectors     sectors per galaxy
     * @param int $systems     systems per sector
     * @param int $seed        PRNG seed
     */
    public static function generate(string $universeId, int $galaxies, int $sectors, int $systems, int $seed = 1337): array
    {
        $db = Database::connection();
        $existing = $db->query('SELECT COUNT(*) AS c FROM star_systems WHERE universe_id = ?', [$universeId])->fetch();
        if ((int) ($existing['c'] ?? 0) > 0) {
            return ['generated' => 0, 'note' => 'universe already populated'];
        }

        $prng = $seed;
        $stmt = $db->prepare(
            'INSERT INTO star_systems (id, universe_id, galaxy, sector, system, name, coordinates)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $generated = 0;
        $names = ['Sol', 'Vega', 'Rigel', 'Betelgeuse', 'Antares', 'Altair', 'Deneb', 'Procyon', 'Arcturus', 'Sirius', 'Aldebaran', 'Polaris', 'Orion', 'Lyra', 'Andromeda', 'Cassiopeia', 'Perseus', 'Hydra', 'Centauri', 'Taurus'];

        for ($g = 1; $g <= $galaxies; $g++) {
            for ($s = 1; $s <= $sectors; $s++) {
                for ($sy = 1; $sy <= $systems; $sy++) {
                    $id = \StellarDominion\Core\UUID::v5('system', "$g:$s:$sy");
                    $name = $names[(int) floor(self::seededRandom($prng) * count($names))];
                    $stmt->execute([
                        $id,
                        $universeId,
                        $g, $s, $sy,
                        $name,
                        "[$g:$s:$sy]",
                    ]);
                    $generated++;
                }
            }
        }

        return ['generated' => $generated];
    }

    /** Find the home system ([1:1:1]) for new players. */
    public static function homeSystem(): ?array
    {
        $row = Database::connection()->query(
            "SELECT * FROM star_systems WHERE galaxy = 1 AND sector = 1 AND system = 1 LIMIT 1"
        )->fetch();
        return $row !== false ? $row : null;
    }

    /** Find a free planet slot in a system (planet without owner). */
    public static function freePlanet(string $systemId): ?array
    {
        return Database::connection()->query(
            'SELECT * FROM planets WHERE system_id = ? AND owner_id IS NULL ORDER BY position ASC LIMIT 1',
            [$systemId]
        )->fetch() ?: null;
    }

    /** Claim a planet for a player. */
    public static function claim(string $planetId, string $playerId): void
    {
        $db = Database::connection();
        $db->prepare('UPDATE planets SET owner_id = ? WHERE id = ?')
            ->execute([$playerId, $planetId]);
        $db->prepare('UPDATE player_states SET coordinates = ?, setup_complete = 1, updated_at = NOW() WHERE id = ?')
            ->execute([
                $db->query('SELECT CONCAT("[", galaxy, ":", sector, ":", system, "]") AS coords FROM star_systems WHERE id = (SELECT system_id FROM planets WHERE id = ?)', [$planetId])->fetch()['coords'],
                $playerId,
            ]);
    }
}
