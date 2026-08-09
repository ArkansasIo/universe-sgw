<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\FleetEngine;
use StellarDominion\Engine\GameData;
use StellarDominion\Models\Fleet;
use StellarDominion\Models\Mission;
use StellarDominion\Models\PlayerState;

/**
 * Fleet endpoints: shipyard catalog, building ships, launching missions,
 * listing fleets/missions.
 *
 * Port of routes-ogame-fleet.ts + fleetMissionService.
 */
final class FleetController extends BaseController
{
    /** Shipyard catalog with build costs/times. */
    public function catalog(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $buildings = $state->buildings();
        $shipyard = (int) ($buildings['shipyard'] ?? 0);
        $robotics = (int) ($buildings['robotics_factory'] ?? 0);

        $catalog = [];
        foreach (GameData::unitDatabase() as $key => $unit) {
            if ($unit['unitType'] !== 'ship') {
                continue;
            }
            $catalog[$key] = [
                'id' => $key,
                'name' => $unit['name'],
                'cost' => ['metal' => $unit['metalCost'], 'crystal' => $unit['crystalCost'], 'deuterium' => $unit['deuteriumCost']],
                'stats' => [
                    'structuralIntegrity' => $unit['structuralIntegrity'],
                    'shield' => $unit['shield'],
                    'attack' => $unit['attack'],
                    'speed' => $unit['speed'],
                    'capacity' => $unit['capacity'],
                    'fuelConsumption' => $unit['fuelConsumption'],
                ],
                'buildTime' => FleetEngine::buildTime($key, 1, $shipyard, $robotics),
            ];
        }

        return $this->json(['ships' => $catalog, 'owned' => $state->units()]);
    }

    /** Build units (instant in the simplified port; cost deducted). */
    public function build(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $unitsMap = $request->input('units', []);
        if (!is_array($unitsMap) || $unitsMap === []) {
            throw HttpException::badRequest('Provide a units map, e.g. {"light_fighter": 10}.');
        }

        $buildings = $state->buildings();
        $shipyard = (int) ($buildings['shipyard'] ?? 0);

        try {
            $result = FleetEngine::buildUnits($state->id, $unitsMap, $shipyard);
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }

        return $this->json($result, 'Ships built', 202);
    }

    /** Launch a fleet mission. */
    public function launch(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $origin = (string) $request->input('origin', '');
        $destination = (string) $request->input('destination', '');
        $ships = $request->input('ships', []);
        $mission = (string) $request->input('mission', 'attack');
        $cargo = $request->input('cargo', []);

        if (!is_array($ships) || $ships === []) {
            throw HttpException::badRequest('Provide a ships map.');
        }
        $allowedMissions = ['attack', 'transport', 'colonize', 'espionage', 'defend', 'expedition'];
        if (!in_array($mission, $allowedMissions, true)) {
            throw HttpException::badRequest("Unknown mission: $mission");
        }

        try {
            $result = FleetEngine::launchMission($state->id, $origin, $destination, $ships, $mission, is_array($cargo) ? $cargo : []);
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }

        return $this->json($result, 'Mission launched', 202);
    }

    public function list(Request $request): Response
    {
        $user = $this->currentUser($request);
        return $this->json([
            'fleets' => Fleet::forPlayer($user->id),
            'missions' => Mission::forPlayer($user->id),
            'activeMissions' => Mission::active($user->id),
        ]);
    }
}
