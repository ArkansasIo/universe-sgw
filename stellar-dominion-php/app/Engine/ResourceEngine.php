<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Config;
use StellarDominion\Core\Database;

/**
 * Resource production + storage logic.
 *
 * Faithful port of server/services/gameJobs.ts resourceTickHandler and
 * classic OGame storage formulas.
 */
final class ResourceEngine
{
    /**
     * Apply offline/elapsed resource production for one player state row.
     *
     * @param array $state   player_states row (resources, buildings, last_resource_update)
     * @param int   $nowMs   unix milliseconds to compute production up to
     * @return array{resources:array,last_resource_update:string}
     */
    public static function produce(array $state, int $nowMs): array
    {
        $buildings = is_array($state['buildings'] ?? null) ? $state['buildings'] : ((array) json_decode((string) ($state['buildings'] ?? '{}'), true) ?: []);
        $resources = is_array($state['resources'] ?? null) ? $state['resources'] : ((array) json_decode((string) ($state['resources'] ?? '{}'), true) ?: []);

        $lastUpdate = is_string($state['last_resource_update'] ?? null)
            ? strtotime($state['last_resource_update']) * 1000
            : (int) ($state['last_resource_update'] ?? $nowMs);

        $elapsedMs = max(0, $nowMs - $lastUpdate);
        if ($elapsedMs < 3000) {
            return ['resources' => $resources, 'last_resource_update' => $state['last_resource_update'] ?? date('Y-m-d H:i:s')];
        }

        $maxOfflineHours = (float) Config::get('game.max_offline_production_hours', 24);
        $elapsedHours = min($maxOfflineHours, $elapsedMs / 3_600_000);

        $metalMine = (int) ($buildings['metal_mine'] ?? 0);
        $crystalMine = (int) ($buildings['crystal_mine'] ?? 0);
        $deuteriumSynth = (int) ($buildings['deuterium_synthesizer'] ?? 0);
        $solarPlant = (int) ($buildings['solar_plant'] ?? 0);
        $fusionPlant = (int) ($buildings['fusion_plant'] ?? 0);
        $solarSatellites = (int) ($buildings['solar_satellite'] ?? 0);

        $metalProd = (int) floor($metalMine * (1 + $metalMine / 10) * $elapsedHours);
        $crystalProd = (int) floor($crystalMine * (1 + $crystalMine / 10) * $elapsedHours);
        $deuteriumProd = (int) floor($deuteriumSynth * (1 + $deuteriumSynth / 12) * $elapsedHours);

        // Energy: solar plant + fusion plant + solar satellites, minus upkeep
        $energyProd = (int) floor($solarPlant * (1 + $solarPlant / 10) * $elapsedHours);
        if ($fusionPlant > 0) {
            $factor = 1.05; // base fusion factor (energy tech / plasma tech raise it)
            $energyProd += (int) floor(30 * $fusionPlant * pow($factor, $fusionPlant) * $elapsedHours);
        }
        // Solar satellites: temperature-dependent, average ~ 10 energy each
        $energyProd += (int) floor($solarSatellites * 10 * $elapsedHours);

        $energyConsumed = (int) floor((10 * $metalMine + 10 * $crystalMine + 20 * $deuteriumSynth) * $elapsedHours);
        $netEnergy = max(0, $energyProd - $energyConsumed);

        $resources['metal'] = (int) (($resources['metal'] ?? 0) + $metalProd);
        $resources['crystal'] = (int) (($resources['crystal'] ?? 0) + $crystalProd);
        $resources['deuterium'] = (int) (($resources['deuterium'] ?? 0) + $deuteriumProd);
        $resources['energy'] = $netEnergy;

        // Storage caps
        foreach (['metal' => 'metal_storage', 'crystal' => 'crystal_storage', 'deuterium' => 'deuterium_tank'] as $res => $storageBuilding) {
            $cap = self::storageCapacity((int) ($buildings[$storageBuilding] ?? 0));
            $resources[$res] = min($cap, $resources[$res]);
        }

        return [
            'resources' => $resources,
            'last_resource_update' => date('Y-m-d H:i:s', (int) floor($nowMs / 1000)),
        ];
    }

    /** Classic OGame storage: base 100k + 50k per round(1.6^level - 1). */
    public static function storageCapacity(int $level): int
    {
        if ($level <= 0) {
            return 100_000;
        }
        return 100_000 + 50_000 * ((int) ceil(pow(1.6, $level)) - 1);
    }

    /** Apply production for a batch of players (used by cron/tick.php). */
    public static function tickBatch(int $limit = 50): int
    {
        $db = Database::connection();
        $rows = $db->query(
            'SELECT id, resources, buildings, last_resource_update, empire_level
             FROM player_states
             ORDER BY last_resource_update ASC
             LIMIT ' . max(1, (int) $limit)
        )->fetchAll();

        $nowMs = (int) floor(microtime(true) * 1000);
        $updated = 0;
        $stmt = $db->prepare('UPDATE player_states SET resources = ?, last_resource_update = ?, updated_at = NOW() WHERE id = ?');

        foreach ($rows as $row) {
            $result = self::produce($row, $nowMs);
            $stmt->execute([json_encode($result['resources']), $result['last_resource_update'], $row['id']]);
            $updated++;
        }

        return $updated;
    }
}
