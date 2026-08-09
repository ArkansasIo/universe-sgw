<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Database;

/**
 * Expedition system: launches, encounters, rewards.
 *
 * Port of routes-expeditions.ts / expeditionService.ts. Expeditions take a
 * fleet to a destination and run random encounters with rewards or combat
 * versus one of the enemy races.
 */
final class ExpeditionEngine
{
    /**
     * Generate a random encounter for an expedition.
     *
     * @param array $fleet  ships composition keyed by machineName
     * @return array{type:string, rewards:array, enemyRace?:string, description:string}
     */
    public static function generateEncounter(array $fleet, int $tier = 1, ?string $forceType = null): array
    {
        $types = ['resources', 'artifacts', 'combat', 'empty', 'rare_planet'];
        $type = $forceType ?? $types[random_int(0, count($types) - 1)];

        $strength = 0;
        foreach ($fleet as $key => $count) {
            $unit = GameData::unit((string) $key);
            if ($unit) {
                $strength += ($unit['metalCost'] + $unit['crystalCost'] + $unit['deuteriumCost']) * (int) $count;
            }
        }

        switch ($type) {
            case 'resources':
                $scale = max(100, (int) floor($strength * (0.2 + mt_rand(0, 30) / 100) * $tier));
                return [
                    'type' => 'resources',
                    'description' => 'The expedition discovers a derelict cargo freighter.',
                    'rewards' => [
                        'metal' => $scale,
                        'crystal' => (int) floor($scale * 0.6),
                        'deuterium' => (int) floor($scale * 0.3),
                    ],
                ];

            case 'artifacts':
                return [
                    'type' => 'artifacts',
                    'description' => 'A mysterious signal leads to an ancient cache.',
                    'rewards' => ['credits' => (int) floor(($strength / 100) * (1 + $tier))],
                ];

            case 'rare_planet':
                return [
                    'type' => 'rare_planet',
                    'description' => 'Survey data reveals a valuable uninhabited world.',
                    'rewards' => ['colony_candidate' => true],
                ];

            case 'empty':
                return ['type' => 'empty', 'description' => 'The sector is empty.', 'rewards' => []];

            case 'combat':
            default:
                $races = GameData::enemyRaces();
                $raceKeys = array_keys($races);
                $raceKey = $raceKeys[random_int(0, count($raceKeys) - 1)];
                return [
                    'type' => 'combat',
                    'description' => 'Hostile ships detected!',
                    'rewards' => [],
                    'enemyRace' => $raceKey,
                ];
        }
    }

    /**
     * Resolve a combat encounter against an enemy race fleet.
     * Returns battle result using the shared CombatEngine.
     */
    public static function resolveCombatEncounter(array $playerFleet, string $raceKey, int $tier = 1): array
    {
        $races = GameData::enemyRaces();
        $race = $races[$raceKey] ?? $races['reavers'];

        $enemyShips = [];
        $playerStrength = 0;
        foreach ($playerFleet as $key => $count) {
            $unit = GameData::unit((string) $key);
            if ($unit) {
                $playerStrength += ($unit['metalCost'] + $unit['crystalCost'] + $unit['deuteriumCost']) * (int) $count;
            }
        }

        $targetStrength = (int) ($playerStrength * ($race['power_scale'] ?? 1.0) * $tier);
        foreach (($race['ships'] ?? ['light_fighter']) as $shipKey) {
            $unit = GameData::unit((string) $shipKey);
            if (!$unit) {
                continue;
            }
            $value = $unit['metalCost'] + $unit['crystalCost'] + $unit['deuteriumCost'];
            $count = max(1, (int) floor($targetStrength / count($race['ships']) / max(1, $value)));
            $enemyShips[] = [$shipKey, $count];
        }

        $playerUnits = [];
        foreach ($playerFleet as $key => $count) {
            $playerUnits[] = [(string) $key, (int) $count];
        }

        return CombatEngine::simulateBattle(
            [[
                'fleetMissionId' => 'player',
                'ownerId' => 'player',
                'units' => $playerUnits,
                'weaponTech' => 0, 'shieldTech' => 0, 'armorTech' => 0,
            ]],
            [[
                'fleetMissionId' => 'enemy',
                'ownerId' => 'enemy_' . $raceKey,
                'units' => $enemyShips,
                'weaponTech' => 0, 'shieldTech' => 0, 'armorTech' => 0,
            ]],
            ['metal' => 0, 'crystal' => 0, 'deuterium' => 0]
        );
    }

    public static function tickBatch(int $limit = 30): int
    {
        $db = Database::connection();
        $rows = $db->query(
            "SELECT * FROM expeditions WHERE status = 'active' AND completed_at IS NULL LIMIT " . max(1, (int) $limit)
        )->fetchAll();

        $processed = 0;
        foreach ($rows as $row) {
            $fleet = $row['fleet_comp'] ? json_decode($row['fleet_comp'], true) : [];
            $encounter = self::generateEncounter(is_array($fleet) ? $fleet : [], (int) $row['tier']);

            if ($encounter['type'] === 'combat') {
                $battle = self::resolveCombatEncounter(is_array($fleet) ? $fleet : [], $encounter['enemyRace'], (int) $row['tier']);
                $survivors = $battle['playerUnitsResult'] ?? [];

                // reduce fleet to survivors
                $fleet = [];
                foreach ($survivors as $key => $count) {
                    $fleet[$key] = $count;
                }
                $encounter['battle'] = $battle;
            }

            $discoveries = $row['discoveries'] ? json_decode($row['discoveries'], true) : [];
            $discoveries[] = $encounter;

            $db->prepare(
                "UPDATE expeditions SET discoveries = ?, status = 'completed', completed_at = NOW() WHERE id = ?"
            )->execute([json_encode($discoveries), $row['id']]);

            $processed++;
        }

        return $processed;
    }
}
