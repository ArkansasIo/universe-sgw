<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Config;
use StellarDominion\Core\Database;

/**
 * Fleet management: building units, travel times and missions.
 *
 * Port of the classic OGame fleet/travel formulas (routes-ogame-fleet.ts,
 * ogameTravelService.ts) adapted to the player_states JSON layout.
 */
final class FleetEngine
{
    /**
     * Cost to build `count` of a ship at the current level of that ship in
     * the unit queue (classic build-cost growth per unit).
     */
    public static function unitCost(string $unitKey, int $alreadyBuilt = 0): array
    {
        $unit = GameData::unit($unitKey);
        if ($unit === null) {
            return ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        }
        $cost = [
            'metal' => $unit['metalCost'],
            'crystal' => $unit['crystalCost'],
            'deuterium' => $unit['deuteriumCost'],
        ];
        return $cost;
    }

    /**
     * Build time in seconds for a unit count.
     * Classic OGame: (sumCost * count * buildTimeFactor) / (shipyard * robotics),
     * scaled to seconds.
     */
    public static function buildTime(string $unitKey, int $count, int $shipyardLevel = 1, int $roboticsLevel = 0, int $naniteLevel = 0): int
    {
        $unit = GameData::unit($unitKey);
        if ($unit === null) {
            return 0;
        }
        $cost = self::unitCost($unitKey);
        $sum = $cost['metal'] + $cost['crystal'];
        $base = (int) ($unit['build_time'] ?? 4);
        $multiplier = 1 + $shipyardLevel * 0.05 + $roboticsLevel * 0.10 + $naniteLevel * 0.50;
        return max(1, (int) floor($sum * $count / $base / $multiplier));
    }

    /**
     * Travel time in seconds between two coordinates.
     * Coordinates are '[galaxy:sector:system]'. Uses the classic OGame
     * formula: distance-based, divided by speed, plus offset.
     */
    public static function travelTime(array $from, array $to, int $shipSpeed, float $speedFactor = 1.0, int $impulseDrive = 0): int
    {
        [$g1, $s1, $sy1] = array_map('intval', $from);
        [$g2, $s2, $sy2] = array_map('intval', $to);

        $distance = self::distance($g1, $s1, $sy1, $g2, $s2, $sy2);

        // speed bonuses from drives (classic: +10% combustion, +20% impulse, +30% hyperspace)
        $finalSpeed = $shipSpeed * $speedFactor;
        $travelSeconds = (int) floor($distance * 3500 / max(1, $finalSpeed) + 10);

        return max(1, $travelSeconds);
    }

    /** Distance in the OGame coordinate space. */
    public static function distance(int $g1, int $s1, int $sy1, int $g2, int $s2, int $sy2): int
    {
        $a = 20_000 * abs($g1 - $g2) + 5 * abs($s1 - $s2) + abs($sy1 - $sy2);
        if ($g1 === $g2) {
            return abs($sy1 - $sy2) + abs($s1 - $s2);
        }
        return $a;
    }

    /** Parse '[g:s:s]' string into int array. Returns null when malformed. */
    public static function parseCoordinates(string $coords): ?array
    {
        if (preg_match('/^\[(\d+):(\d+):(\d+)\]$/', trim($coords), $m)) {
            return [(int) $m[1], (int) $m[2], (int) $m[3]];
        }
        return null;
    }

    /** Compute total cargo capacity of a fleet composition. */
    public static function cargoCapacity(array $ships): int
    {
        $total = 0;
        foreach ($ships as $key => $count) {
            $unit = GameData::unit((string) $key);
            if ($unit) {
                $total += ($unit['capacity'] ?? 0) * (int) $count;
            }
        }
        return $total;
    }

    /** Compute max travel speed of a fleet (min engine speed used). */
    public static function fleetSpeed(array $ships): int
    {
        $speed = null;
        foreach ($ships as $key => $count) {
            if ((int) $count <= 0) {
                continue;
            }
            $unit = GameData::unit((string) $key);
            if ($unit && ($unit['speed'] ?? 0) > 0) {
                $speed = $speed === null ? (int) $unit['speed'] : min($speed, (int) $unit['speed']);
            }
        }
        return $speed ?? 0;
    }

    /**
     * Add ships to a fleet (build action). Deducts cost, appends to
     * player_states.units (or a cron_jobs fleet queue item).
     */
    public static function buildUnits(string $playerId, array $unitsMap, int $shipyardLevel = 1): array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT units, resources, buildings FROM player_states WHERE id = ?');
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new \RuntimeException('Player state not found.');
        }

        $ships = $row['units'] ? json_decode($row['units'], true) : [];
        $resources = $row['resources'] ? json_decode($row['resources'], true) : [];
        $buildings = $row['buildings'] ? json_decode($row['buildings'], true) : [];

        $totalCost = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        $queueItems = [];

        foreach ($unitsMap as $key => $count) {
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            $unit = GameData::unit((string) $key);
            if ($unit === null) {
                throw new \RuntimeException("Unknown unit: $key");
            }
            $cost = self::unitCost((string) $key);
            foreach (['metal', 'crystal', 'deuterium'] as $res) {
                $totalCost[$res] += $cost[$res] * $count;
            }
        }

        if (!ConstructionEngine::canAfford(is_array($resources) ? $resources : [], $totalCost)) {
            throw new \RuntimeException('Insufficient resources.');
        }

        ConstructionEngine::deduct($resources, $totalCost);

        foreach ($unitsMap as $key => $count) {
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            $queueItems[] = [
                'type' => 'fleet',
                'unitType' => $key,
                'count' => $count,
                'completeAt' => (int) floor(microtime(true) * 1000)
                    + self::buildTime($key, $count, $shipyardLevel) * 1000,
            ];
            // Instantly add to fleet (simplified: no separate shipyard queue)
            $ships[$key] = (int) ($ships[$key] ?? 0) + $count;
        }

        $db->prepare('UPDATE player_states SET units = ?, resources = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($ships), json_encode($resources), $playerId]);

        return ['units' => $ships, 'cost' => $totalCost, 'queueItems' => $queueItems];
    }

    /**
     * Launch a fleet mission from origin to destination.
     *
     * @param array  $ships      composition being sent (subset of units)
     * @param array  $cargo      resources loaded {metal,crystal,deuterium}
     */
    public static function launchMission(string $playerId, string $origin, string $destination, array $ships, string $mission, array $cargo = []): array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT units FROM player_states WHERE id = ?');
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new \RuntimeException('Player state not found.');
        }

        $units = $row['units'] ? json_decode($row['units'], true) : [];
        foreach ($ships as $key => $count) {
            if ((int) ($units[$key] ?? 0) < (int) $count) {
                throw new \RuntimeException("Not enough $key.");
            }
        }

        $fromCoords = self::parseCoordinates($origin);
        $toCoords = self::parseCoordinates($destination);
        if ($fromCoords === null || $toCoords === null) {
            throw new \RuntimeException('Invalid coordinates.');
        }

        $speed = self::fleetSpeed($ships);
        $travelSeconds = self::travelTime($fromCoords, $toCoords, $speed);
        $nowMs = (int) floor(microtime(true) * 1000);

        foreach ($ships as $key => $count) {
            $units[$key] = (int) ($units[$key] ?? 0) - (int) $count;
        }

        $missionId = \StellarDominion\Core\UUID::v4();
        $db->prepare(
            'INSERT INTO missions (id, fleet_id, user_id, type, status, target, origin, units, cargo, departure_time, arrival_time, return_time)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)'
        )->execute([
            $missionId,
            $playerId,
            $mission,
            'outbound',
            $destination,
            $origin,
            json_encode($ships),
            json_encode($cargo),
            date('Y-m-d H:i:s', (int) floor($nowMs / 1000)),
            date('Y-m-d H:i:s', (int) floor(($nowMs + $travelSeconds * 1000) / 1000)),
        ]);

        $db->prepare('UPDATE player_states SET units = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($units), $playerId]);

        return [
            'missionId' => $missionId,
            'travelSeconds' => $travelSeconds,
            'arrivalTime' => date('Y-m-d H:i:s', (int) floor(($nowMs + $travelSeconds * 1000) / 1000)),
        ];
    }

    /** Process missions whose arrival time has passed (expedition/attack etc.). */
    public static function processArrivals(?int $now = null): int
    {
        $db = Database::connection();
        $now = $now ?? time();

        $rows = $db->query(
            "SELECT * FROM missions WHERE status = 'outbound' AND arrival_time IS NOT NULL AND arrival_time <= NOW()"
        )->fetchAll();

        $processed = 0;
        foreach ($rows as $row) {
            $ships = $row['units'] ? json_decode($row['units'], true) : [];
            $cargo = $row['cargo'] ? json_decode($row['cargo'], true) : [];

            switch ($row['type']) {
                case 'transport':
                case 'colonize':
                    self::deliverResources((string) $row['target'], is_array($cargo) ? $cargo : [], (string) $row['user_id']);
                    break;
                case 'attack':
                    $result = self::resolveAttack($row);
                    $db->prepare("UPDATE missions SET status = 'completed', processed = 1, result = ?, return_time = NOW() WHERE id = ?")
                        ->execute([json_encode($result), $row['id']]);
                    $processed++;
                    continue 2;
                case 'expedition':
                    break; // handled by ExpeditionEngine
                default:
                    break;
            }

            $db->prepare("UPDATE missions SET status = 'completed', processed = 1, return_time = NOW() WHERE id = ?")
                ->execute([$row['id']]);
            $processed++;
        }

        return $processed;
    }

    private static function deliverResources(string $targetCoords, array $cargo, string $senderId): void
    {
        $db = Database::connection();
        $row = $db->query(
            "SELECT id FROM player_states WHERE coordinates = ?",
            [$targetCoords]
        )->fetch();

        if ($row === false) {
            return;
        }
        $resources = $row['resources'] ? json_decode($row['resources'], true) : [];
        foreach (['metal', 'crystal', 'deuterium'] as $res) {
            $resources[$res] = (int) ($resources[$res] ?? 0) + (int) ($cargo[$res] ?? 0);
        }
        $db->prepare('UPDATE player_states SET resources = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($resources), $row['id']]);
    }

    /** Resolve an attack mission: run combat, apply losses/loot. */
    private static function resolveAttack(array $mission): array
    {
        $db = Database::connection();
        $ships = $mission['units'] ? json_decode($mission['units'], true) : [];

        $defenderRow = $db->query(
            "SELECT * FROM player_states WHERE coordinates = ?",
            [$mission['target']]
        )->fetch();

        if ($defenderRow === false) {
            return ['winner' => 'attacker', 'note' => 'no_defender'];
        }

        $attackerUnits = [];
        foreach (is_array($ships) ? $ships : [] as $key => $count) {
            $attackerUnits[] = [$key, (int) $count];
        }

        $defenderUnits = [];
        $defenderFleet = $defenderRow['units'] ? json_decode($defenderRow['units'], true) : [];
        $defenderDefenses = $defenderRow['buildings'] ? json_decode($defenderRow['buildings'], true) : [];

        $defenseKeys = [
            'rocket_launcher', 'light_laser', 'heavy_laser', 'ion_cannon',
            'gauss_cannon', 'plasma_turret', 'small_shield_dome', 'large_shield_dome',
        ];
        foreach ($defenseKeys as $key) {
            $count = (int) ($defenderDefenses[$key] ?? 0);
            if ($count > 0) {
                $defenderUnits[] = [$key, $count];
            }
        }
        // also include defender's parked ships as defenders
        foreach (is_array($defenderFleet) ? $defenderFleet : [] as $key => $count) {
            if ((int) $count > 0) {
                $defenderUnits[] = [$key, (int) $count];
            }
        }

        $defenderResources = $defenderRow['resources'] ? json_decode($defenderRow['resources'], true) : [];

        $result = CombatEngine::simulateBattle(
            [[
                'fleetMissionId' => $mission['id'],
                'ownerId' => $mission['user_id'],
                'units' => $attackerUnits,
                'weaponTech' => 0, 'shieldTech' => 0, 'armorTech' => 0,
            ]],
            [[
                'fleetMissionId' => 'defender',
                'ownerId' => $defenderRow['user_id'],
                'units' => $defenderUnits,
                'weaponTech' => 0, 'shieldTech' => 0, 'armorTech' => 0,
            ]],
            $defenderResources
        );

        // Apply defender losses to defenses
        if ($result['winner'] !== 'attacker') {
            foreach (($result['defenderUnitsLost'] ?? []) as $key => $lost) {
                if (in_array($key, $defenseKeys, true)) {
                    $defenderDefenses[$key] = max(0, (int) ($defenderDefenses[$key] ?? 0) - $lost);
                } else {
                    $defenderFleet[$key] = max(0, (int) ($defenderFleet[$key] ?? 0) - $lost);
                }
            }
            $db->prepare('UPDATE player_states SET buildings = ?, units = ?, updated_at = NOW() WHERE id = ?')
                ->execute([json_encode($defenderDefenses), json_encode($defenderFleet), $defenderRow['id']]);
        }

        // Apply loot to attacker
        if (($result['winner'] ?? '') === 'attacker') {
            $loot = $result['loot'];
            $attackerState = $db->query('SELECT resources FROM player_states WHERE id = ?', [$mission['user_id']])->fetch();
            if ($attackerState !== false) {
                $attackerResources = $attackerState['resources'] ? json_decode($attackerState['resources'], true) : [];
                foreach (['metal', 'crystal', 'deuterium'] as $res) {
                    $attackerResources[$res] = (int) ($attackerResources[$res] ?? 0) + (int) ($loot[$res] ?? 0);
                }
                $db->prepare('UPDATE player_states SET resources = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([json_encode($attackerResources), $mission['user_id']]);
            }
        }

        return $result;
    }
}
