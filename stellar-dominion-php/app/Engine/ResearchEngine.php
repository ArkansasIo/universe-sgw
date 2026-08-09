<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Database;

/**
 * Research system: costs, times, prerequisites and the active research queue.
 *
 * Port of the original research routes (routes-research.ts) and the
 * research_progress + research_queue JSON structures on player_states.
 */
final class ResearchEngine
{
    /** Next-level research cost (classic growth factor). */
    public static function researchCost(string $techKey, int $level): array
    {
        $tech = GameData::technology($techKey);
        if ($tech === null) {
            return ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        }
        $growth = $tech['growth'] ?? 2.0;
        $base = $tech['cost'];

        return [
            'metal' => (int) floor(($base['metal'] ?? 0) * pow($growth, $level)),
            'crystal' => (int) floor(($base['crystal'] ?? 0) * pow($growth, $level)),
            'deuterium' => (int) floor(($base['deuterium'] ?? 0) * pow($growth, $level)),
        ];
    }

    /**
     * Research time in seconds.
     * Original formula: (metal + crystal) * time_base / (lab_level + 1),
     * where time_base is per-research seconds unit.
     */
    public static function researchTime(string $techKey, int $level, int $labLevel = 1): int
    {
        $tech = GameData::technology($techKey);
        if ($tech === null) {
            return 3600;
        }
        $cost = self::researchCost($techKey, $level);
        $sum = $cost['metal'] + $cost['crystal'];
        $divisor = max(1, $labLevel + 1);
        return max(5, (int) floor($sum / 10 / $divisor));
    }

    /** Check whether a player meets prerequisites for a technology. */
    public static function meetsPrerequisites(array $research, string $techKey): bool
    {
        $tech = GameData::technology($techKey);
        if ($tech === null) {
            return false;
        }
        foreach (($tech['prerequisites'] ?? []) as $requiredTech => $requiredLevel) {
            if (($research[$requiredTech] ?? 0) < $requiredLevel) {
                return false;
            }
        }
        return true;
    }

    /** Check whether a player meets prerequisite research for building/unit. */
    public static function meetsRequirements(array $research, array $requirements): bool
    {
        foreach ($requirements as $requiredTech => $requiredLevel) {
            if (($research[$requiredTech] ?? 0) < $requiredLevel) {
                return false;
            }
        }
        return true;
    }

    /**
     * Enqueue a research item. Returns remaining cost or throws on failure.
     */
    public static function startResearch(string $playerId, string $techKey, array $research, array $researchQueue, array &$resources): array
    {
        $currentLevel = (int) ($research[$techKey] ?? 0);
        if (!self::meetsPrerequisites($research, $techKey)) {
            throw new \RuntimeException('Prerequisites not met.');
        }

        foreach ($researchQueue as $item) {
            if (($item['techKey'] ?? null) === $techKey) {
                throw new \RuntimeException('Research already in queue.');
            }
        }

        $labLevel = (int) ($research['research_lab'] ?? 1); // building level drives speed
        $cost = self::researchCost($techKey, $currentLevel);
        if (!ConstructionEngine::canAfford($resources, $cost)) {
            throw new \RuntimeException('Insufficient resources.');
        }

        $completeAt = (int) floor(microtime(true) * 1000) + self::researchTime($techKey, $currentLevel, $labLevel) * 1000;

        ConstructionEngine::deduct($resources, $cost);
        $researchQueue[] = [
            'techKey' => $techKey,
            'targetLevel' => $currentLevel + 1,
            'completeAt' => $completeAt,
        ];

        return ['queue' => $researchQueue, 'cost' => $cost, 'completeAt' => $completeAt];
    }

    /**
     * Advance the research queue (research_queue JSON), returning updated
     * structures.
     */
    public static function processQueue(array $research, array $researchQueue, int $nowMs): array
    {
        $completed = 0;
        $remaining = [];

        foreach ($researchQueue as $item) {
            $completeAt = (int) ($item['completeAt'] ?? 0);
            if ($completeAt > 0 && $completeAt <= $nowMs) {
                $techKey = (string) ($item['techKey'] ?? '');
                if ($techKey !== '') {
                    $research[$techKey] = (int) ($research[$techKey] ?? 0) + 1;
                }
                $completed++;
            } else {
                $remaining[] = $item;
            }
        }

        return ['research' => $research, 'researchQueue' => $remaining, 'completed' => $completed];
    }

    /** Persist a research queue update for a player. */
    public static function save(string $playerId, array $research, array $researchQueue): void
    {
        $db = Database::connection();
        $db->prepare('UPDATE player_states SET research = ?, research_queue = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($research), json_encode($researchQueue), $playerId]);
    }

    public static function tickBatch(int $limit = 75): int
    {
        $db = Database::connection();
        $rows = $db->query(
            'SELECT id, research, research_queue FROM player_states
             WHERE JSON_LENGTH(research_queue) > 0 LIMIT ' . max(1, (int) $limit)
        )->fetchAll();

        $nowMs = (int) floor(microtime(true) * 1000);
        $total = 0;

        foreach ($rows as $row) {
            $research = $row['research'] ? json_decode($row['research'], true) : [];
            $queue = $row['research_queue'] ? json_decode($row['research_queue'], true) : [];
            $result = self::processQueue(is_array($research) ? $research : [], is_array($queue) ? $queue : [], $nowMs);
            if ($result['completed'] > 0) {
                self::save($row['id'], $result['research'], $result['researchQueue']);
                $total += $result['completed'];
            }
        }

        return $total;
    }
}
