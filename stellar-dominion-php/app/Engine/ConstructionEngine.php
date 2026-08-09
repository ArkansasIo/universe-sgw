<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Config;
use StellarDominion\Core\Database;

/**
 * Building construction: cost/time formulas and the construction queue.
 *
 * Port of gameJobs.ts constructionTickHandler plus the classic OGame
 * build-cost growth formulas used by the original catalogs.
 */
final class ConstructionEngine
{
    /** Next-level cost of a building given its base cost and current level. */
    public static function buildCost(string $buildingKey, int $level): array
    {
        $def = GameData::building($buildingKey);
        if ($def === null) {
            return ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        }

        $growth = $def['growth'] ?? 1.5;
        $base = $def['cost'];

        return [
            'metal' => (int) floor($base['metal'] * pow($growth, $level)),
            'crystal' => (int) floor($base['crystal'] * pow($growth, $level)),
            'deuterium' => (int) floor($base['deuterium'] * pow($growth, $level)),
        ];
    }

    /**
     * Construction time in seconds.
     * Original: (metal + crystal) / (robotics_bonus * duration) hours,
     * scaled to seconds for practical gameplay.
     */
    public static function buildTime(string $buildingKey, int $level, int $roboticsLevel = 0, int $naniteLevel = 0): int
    {
        $def = GameData::building($buildingKey);
        if ($def === null) {
            return 0;
        }

        $cost = self::buildCost($buildingKey, $level);
        $sum = $cost['metal'] + $cost['crystal'];

        $roboticsBonus = 1 + $roboticsLevel * 0.10;
        $naniteBonus = 1 + $naniteLevel * 0.50;
        $durationDivisor = (int) ($def['duration'] ?? 20);

        // base seconds: sum / duration (scaled), reduced by robotics/nanite
        $seconds = max(1, (int) floor($sum / $durationDivisor / $roboticsBonus / $naniteBonus));

        return $seconds;
    }

    /** True when the given resources cover the build cost. */
    public static function canAfford(array $resources, array $cost): bool
    {
        foreach (['metal', 'crystal', 'deuterium'] as $res) {
            if (($resources[$res] ?? 0) < ($cost[$res] ?? 0)) {
                return false;
            }
        }
        return true;
    }

    /** Deduct build cost from resources. */
    public static function deduct(array &$resources, array $cost): void
    {
        foreach (['metal', 'crystal', 'deuterium'] as $res) {
            $resources[$res] = (int) (($resources[$res] ?? 0) - ($cost[$res] ?? 0));
        }
    }

    /**
     * Process a construction queue (cron_jobs JSON) and apply completions.
     *
     * @param array  $queue     cron_jobs items [{type:'building',buildingType,completeAt}]
     * @param array  $buildings current building levels map
     * @param int    $nowMs
     * @return array{queue:array,buildings:array,completed:int}
     */
    public static function processQueue(array $queue, array $buildings, int $nowMs): array
    {
        $completed = 0;
        $remaining = [];

        foreach ($queue as $item) {
            if (!is_array($item)) {
                continue;
            }
            $completeAt = (int) ($item['completeAt'] ?? 0);
            if ($completeAt > 0 && $completeAt <= $nowMs) {
                $buildingType = (string) ($item['buildingType'] ?? '');
                if ($buildingType !== '' && ($item['type'] ?? '') === 'building') {
                    $buildings[$buildingType] = (int) ($buildings[$buildingType] ?? 0) + 1;
                }
                $completed++;
            } else {
                $remaining[] = $item;
            }
        }

        return ['queue' => $remaining, 'buildings' => $buildings, 'completed' => $completed];
    }

    /** Enqueue a new building, enforcing the queue size cap. */
    public static function enqueue(string $playerId, string $buildingKey, int $level, int $roboticsLevel = 0): array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT cron_jobs, buildings, resources FROM player_states WHERE id = ?');
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new \RuntimeException('Player state not found.');
        }

        $queue = $row['cron_jobs'] ? json_decode($row['cron_jobs'], true) : [];
        $buildings = $row['buildings'] ? json_decode($row['buildings'], true) : [];
        $resources = $row['resources'] ? json_decode($row['resources'], true) : [];

        $maxQueue = (int) Config::get('game.max_queue_items', 5);
        if (count(is_array($queue) ? $queue : []) >= $maxQueue) {
            throw new \RuntimeException('Construction queue is full.');
        }

        $cost = self::buildCost($buildingKey, $level);
        if (!self::canAfford(is_array($resources) ? $resources : [], $cost)) {
            throw new \RuntimeException('Insufficient resources.');
        }

        $completeAt = (int) floor(microtime(true) * 1000) + self::buildTime($buildingKey, $level, $roboticsLevel) * 1000;

        self::deduct($resources, $cost);
        $queue[] = [
            'type' => 'building',
            'buildingType' => $buildingKey,
            'targetLevel' => $level + 1,
            'completeAt' => $completeAt,
        ];

        $db->prepare('UPDATE player_states SET cron_jobs = ?, resources = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($queue), json_encode($resources), $playerId]);

        return ['queue' => $queue, 'cost' => $cost, 'completeAt' => $completeAt];
    }

    public static function tickBatch(int $limit = 100): int
    {
        $db = Database::connection();
        $rows = $db->query(
            'SELECT id, cron_jobs, buildings FROM player_states
             WHERE JSON_LENGTH(cron_jobs) > 0 LIMIT ' . max(1, (int) $limit)
        )->fetchAll();

        $nowMs = (int) floor(microtime(true) * 1000);
        $total = 0;
        $stmt = $db->prepare('UPDATE player_states SET cron_jobs = ?, buildings = ?, updated_at = NOW() WHERE id = ?');

        foreach ($rows as $row) {
            $queue = $row['cron_jobs'] ? json_decode($row['cron_jobs'], true) : [];
            $buildings = $row['buildings'] ? json_decode($row['buildings'], true) : [];
            $result = self::processQueue(is_array($queue) ? $queue : [], is_array($buildings) ? $buildings : [], $nowMs);
            if ($result['completed'] > 0) {
                $stmt->execute([json_encode($result['queue']), json_encode($result['buildings']), $row['id']]);
                $total += $result['completed'];
            }
        }

        return $total;
    }
}
