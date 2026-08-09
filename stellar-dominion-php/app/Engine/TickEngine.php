<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Database;

/**
 * Orchestrates all recurring game jobs.
 *
 * Port of server/services/gameJobs.ts registerAllGameJobs. In the PHP
 * rework each job is a batch SQL/loop step executed by cron/tick.php,
 * mirroring the DB-polling approach of the original server.
 */
final class TickEngine
{
    /** Run one full tick (all jobs). Returns summary counts. */
    public static function run(): array
    {
        $start = hrtime(true);

        $summary = [
            'resource' => ResourceEngine::tickBatch(),
            'turns' => TurnEngine::tickBatch(),
            'construction' => ConstructionEngine::tickBatch(),
            'research' => ResearchEngine::tickBatch(),
            'missions' => FleetEngine::processArrivals(),
            'expeditions' => ExpeditionEngine::tickBatch(),
            'bank_interest' => EconomyEngine::applyInterest(),
        ];

        $summary['duration_ms'] = (int) floor((hrtime(true) - $start) / 1_000_000);
        self::recordTick('full', $summary);

        return $summary;
    }

    /** Run a single named job. */
    public static function runJob(string $job): array
    {
        $start = hrtime(true);
        $count = match ($job) {
            'resource' => ResourceEngine::tickBatch(),
            'turns' => TurnEngine::tickBatch(),
            'construction' => ConstructionEngine::tickBatch(),
            'research' => ResearchEngine::tickBatch(),
            'missions' => FleetEngine::processArrivals(),
            'expeditions' => ExpeditionEngine::tickBatch(),
            'bank_interest' => EconomyEngine::applyInterest(),
            default => throw new \InvalidArgumentException("Unknown job: $job"),
        };

        $result = ['processed' => $count, 'duration_ms' => (int) floor((hrtime(true) - $start) / 1_000_000)];
        self::recordTick($job, $result);
        return $result;
    }

    private static function recordTick(string $jobKey, array $meta): void
    {
        try {
            $db = Database::connection();
            $db->prepare(
                'INSERT INTO server_game_ticks (job_key, processed_count, duration_ms) VALUES (?, ?, ?)'
            )->execute([$jobKey, $meta['processed'] ?? 0, $meta['duration_ms'] ?? 0]);
        } catch (\Throwable) {
            // Ticks must never crash the cron process.
        }
    }
}
