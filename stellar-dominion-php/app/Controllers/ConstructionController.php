<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\ConstructionEngine;
use StellarDominion\Engine\GameData;
use StellarDominion\Models\PlayerState;

/**
 * Building construction endpoints.
 *
 * Port of routes-ogame.ts (building actions) + construction queue.
 */
final class ConstructionController extends BaseController
{
    /** List all buildings with current levels, costs and build times. */
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $buildings = $state->buildings();
        $robotics = (int) ($buildings['robotics_factory'] ?? 0);
        $nanite = (int) ($buildings['nanite_factory'] ?? 0);

        $catalog = [];
        foreach (GameData::buildings() as $key => $def) {
            $level = (int) ($buildings[$key] ?? 0);
            $catalog[$key] = [
                ...$def,
                'level' => $level,
                'nextCost' => ConstructionEngine::buildCost($key, $level),
                'buildTime' => ConstructionEngine::buildTime($key, $level, $robotics, $nanite),
            ];
        }

        return $this->json(['buildings' => $catalog, 'queue' => $state->json('cron_jobs')]);
    }

    /** Build (or queue) one building level. */
    public function build(Request $request, string $buildingKey): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }
        if (GameData::building($buildingKey) === null) {
            throw HttpException::badRequest("Unknown building: $buildingKey");
        }

        $buildings = $state->buildings();
        $robotics = (int) ($buildings['robotics_factory'] ?? 0);
        $level = (int) ($buildings[$buildingKey] ?? 0);

        $result = ConstructionEngine::enqueue($state->id, $buildingKey, $level, $robotics);

        return $this->json([
            'building' => $buildingKey,
            'targetLevel' => $level + 1,
            'cost' => $result['cost'],
            'completeAt' => $result['completeAt'],
            'queue' => $result['queue'],
        ], 'Construction queued', 202);
    }

    /** Cancel the last queued construction (refund 50%). */
    public function cancel(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $queue = $state->json('cron_jobs');
        if ($queue === []) {
            throw HttpException::badRequest('Queue is empty.');
        }

        $item = array_pop($queue);
        $state->saveJson('cron_jobs', $queue);

        // Refund half of the construction cost
        $resources = $state->resources();
        if (isset($item['buildingType'])) {
            $cost = ConstructionEngine::buildCost((string) $item['buildingType'], max(0, (int) ($item['targetLevel'] ?? 1) - 1));
            foreach (['metal', 'crystal', 'deuterium'] as $res) {
                $resources[$res] = (int) ($resources[$res] ?? 0) + (int) floor(($cost[$res] ?? 0) / 2);
            }
        }
        $state->saveJson('resources', $resources);

        return $this->json(['cancelled' => $item, 'queue' => $queue]);
    }
}
