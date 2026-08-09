<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\ResourceEngine;
use StellarDominion\Models\PlayerState;

/**
 * Player / game-state endpoints.
 *
 * Port of routes-game.ts (player state overview, resources) plus resource
 * action helpers.
 */
final class PlayerController extends BaseController
{
    public function show(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $state->refreshResources();

        return $this->json([
            'user' => $user->toPublicArray(),
            'state' => $state->toArray(),
        ]);
    }

    public function resources(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        return $this->json($state->refreshResources());
    }

    public function buildings(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        return $this->json([
            'buildings' => $state->buildings(),
            'queue' => $state->json('cron_jobs'),
        ]);
    }

    public function research(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        return $this->json([
            'research' => $state->research(),
            'researchQueue' => $state->json('research_queue'),
        ]);
    }

    public function units(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        return $this->json($state->units());
    }

    public function storageCapacity(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $buildings = $state->buildings();
        $caps = [
            'metal' => ResourceEngine::storageCapacity((int) ($buildings['metal_storage'] ?? 0)),
            'crystal' => ResourceEngine::storageCapacity((int) ($buildings['crystal_storage'] ?? 0)),
            'deuterium' => ResourceEngine::storageCapacity((int) ($buildings['deuterium_tank'] ?? 0)),
        ];

        return $this->json($caps);
    }
}
