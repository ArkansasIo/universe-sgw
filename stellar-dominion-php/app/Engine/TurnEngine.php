<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Database;

/**
 * Turn system.
 *
 * Port of gameJobs.ts turnTickHandler. Turns regenerate at
 * `turns_per_minute` per minute, capped by `max_offline_turns`, with a
 * 10% streak bonus after a 10+ streak.
 */
final class TurnEngine
{
    public const TURNS_PER_MINUTE = 4;
    public const MAX_OFFLINE_TURNS = 8640;

    /** Compute new turns from a turns_data blob + last timestamp. */
    public static function generate(array $turnsData, int $nowMs, float $turnsPerMinute = self::TURNS_PER_MINUTE): array
    {
        $lastTurn = (int) ($turnsData['lastTurnTimestamp'] ?? $nowMs);
        $elapsedMinutes = max(0, ($nowMs - $lastTurn) / 60_000);
        $newTurns = (int) min(self::MAX_OFFLINE_TURNS, floor($elapsedMinutes * $turnsPerMinute));

        if ($newTurns <= 0) {
            $turnsData['lastTurnTimestamp'] = $nowMs;
            return $turnsData;
        }

        $currentAvailable = (int) ($turnsData['availableTurns'] ?? 0);
        $currentStreak = (int) ($turnsData['streakTurns'] ?? 0);

        $streakBonus = $currentStreak >= 10 ? (int) floor($newTurns * 0.1) : 0;
        $totalNew = $newTurns + $streakBonus;

        return [
            ...$turnsData,
            'availableTurns' => $currentAvailable + $totalNew,
            'lastTurnTimestamp' => $nowMs,
            'streakTurns' => $currentStreak + $newTurns,
            'totalTurnsGenerated' => (int) ($turnsData['totalTurnsGenerated'] ?? 0) + $totalNew,
        ];
    }

    public static function tickBatch(int $limit = 50): int
    {
        $db = Database::connection();
        $rows = $db->query(
            'SELECT id, turns_data FROM player_states
             WHERE updated_at > NOW() - INTERVAL 7 DAY
             ORDER BY updated_at DESC LIMIT ' . max(1, (int) $limit)
        )->fetchAll();

        $nowMs = (int) floor(microtime(true) * 1000);
        $updated = 0;
        $stmt = $db->prepare('UPDATE player_states SET turns_data = ?, updated_at = NOW() WHERE id = ?');

        foreach ($rows as $row) {
            $turnsData = $row['turns_data'] ? json_decode($row['turns_data'], true) : [];
            $result = self::generate(is_array($turnsData) ? $turnsData : [], $nowMs);
            $stmt->execute([json_encode($result), $row['id']]);
            $updated++;
        }

        return $updated;
    }
}
