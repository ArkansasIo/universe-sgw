<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\GameData;
use StellarDominion\Engine\ResearchEngine;
use StellarDominion\Models\PlayerState;

/**
 * Research endpoints.
 *
 * Port of routes-research.ts. Lists techs with current levels, starts
 * research and reports the active queue.
 */
final class ResearchController extends BaseController
{
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $research = $state->research();
        $catalog = [];

        foreach (GameData::technologies() as $key => $tech) {
            $level = (int) ($research[$key] ?? 0);
            $catalog[$key] = [
                ...$tech,
                'level' => $level,
                'nextCost' => ResearchEngine::researchCost($key, $level),
                'researchTime' => ResearchEngine::researchTime($key, $level, (int) ($research['research_lab'] ?? 1)),
                'available' => ResearchEngine::meetsPrerequisites($research, $key),
            ];
        }

        return $this->json(['technologies' => $catalog, 'researchQueue' => $state->json('research_queue')]);
    }

    public function start(Request $request, string $techKey): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }
        if (GameData::technology($techKey) === null) {
            throw HttpException::badRequest("Unknown technology: $techKey");
        }

        $research = $state->research();
        $queue = $state->json('research_queue');
        $resources = $state->resources();

        try {
            $result = ResearchEngine::startResearch($state->id, $techKey, $research, $queue, $resources);
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }

        ResearchEngine::save($state->id, $research, $result['queue']);
        $state->saveJson('resources', $resources);

        return $this->json($result, 'Research started', 202);
    }
}
