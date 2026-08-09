<?php

declare(strict_types=1);

use StellarDominion\Engine\ConstructionEngine;
use StellarDominion\Engine\FleetEngine;
use StellarDominion\Engine\GameData;
use StellarDominion\Engine\ResourceEngine;
use StellarDominion\Engine\TurnEngine;

// --- GameData / unit database ---------------------------------------------

test('Unit database contains all classic ships', function (): void {
    $db = GameData::unitDatabase();
    foreach (['light_fighter', 'heavy_fighter', 'cruiser', 'battleship', 'battlecruiser', 'bomber', 'destroyer', 'death_star', 'small_cargo', 'large_cargo', 'recycler', 'espionage_probe', 'solar_satellite', 'colony_ship'] as $key) {
        assertTrue(isset($db[$key]), "ship $key exists");
    }
    assertEquals('ship', $db['light_fighter']['unitType']);
    assertEquals(3000, $db['light_fighter']['metalCost']);
    assertEquals(5000, $db['small_cargo']['capacity']);
    assertEquals(25000, $db['large_cargo']['capacity']);
});

test('Unit database contains defenses flagged as defense', function (): void {
    $db = GameData::unitDatabase();
    assertEquals('defense', $db['rocket_launcher']['unitType']);
    assertEquals('defense', $db['plasma_turret']['unitType']);
});

test('GameData loads buildings and technologies', function (): void {
    $buildings = GameData::buildings();
    assertTrue(isset($buildings['metal_mine']), 'metal_mine building exists');
    assertEquals(60, $buildings['metal_mine']['cost']['metal']);

    $techs = GameData::technologies();
    assertTrue(isset($techs['energy_technology']), 'energy_technology exists');
    assertTrue(isset($techs['plasma_technology']['prerequisites']['ion_technology']));
});

test('GameData loads realms and enemy races', function (): void {
    $realms = GameData::realms();
    assertTrue(isset($realms['terran']), 'terran realm exists');
    assertEquals(1.10, $realms['terran']['resource_bonus']['metal']);

    $races = GameData::enemyRaces();
    assertTrue(isset($races['reavers']), 'reavers race exists');
});

// --- Building cost / time --------------------------------------------------

test('Building cost grows exponentially with level', function (): void {
    $cost1 = ConstructionEngine::buildCost('metal_mine', 0);
    $cost2 = ConstructionEngine::buildCost('metal_mine', 1);
    $cost3 = ConstructionEngine::buildCost('metal_mine', 2);

    assertEquals(60, $cost1['metal']);
    assertEquals(90, $cost2['metal'], '60 * 1.5 = 90');
    assertEquals(135, $cost3['metal'], '90 * 1.5 = 135');
});

test('Build time decreases with robotics factory', function (): void {
    $t0 = ConstructionEngine::buildTime('shipyard', 0, 0, 0);
    $t10 = ConstructionEngine::buildTime('shipyard', 0, 10, 0);
    assertLessThan($t0, $t10, 'higher robotics level reduces build time');
});

test('Queue processing completes buildings', function (): void {
    $now = (int) floor(microtime(true) * 1000);
    $queue = [
        ['type' => 'building', 'buildingType' => 'metal_mine', 'completeAt' => $now - 1000],
        ['type' => 'building', 'buildingType' => 'solar_plant', 'completeAt' => $now + 5000],
    ];
    $result = ConstructionEngine::processQueue($queue, [], $now);
    assertEquals(1, $result['completed']);
    assertEquals(1, $result['buildings']['metal_mine']);
    assertEquals(1, count($result['queue']), 'incomplete item stays queued');
});

// --- Resource production ---------------------------------------------------

test('Resource production scales with mine level', function (): void {
    $state = [
        'buildings' => ['metal_mine' => 10, 'crystal_mine' => 5, 'deuterium_synthesizer' => 3, 'solar_plant' => 10],
        'resources' => ['metal' => 100, 'crystal' => 100, 'deuterium' => 100, 'energy' => 0],
        'last_resource_update' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
    ];
    $nowMs = (int) floor(microtime(true) * 1000);
    $result = ResourceEngine::produce($state, $nowMs);

    // metal: 10 * (1 + 10/10) * 1h = 20/h
    assertTrue($result['resources']['metal'] > 100, 'metal increased');
    assertEquals(120, $result['resources']['metal']);
    // energy: solar 10*(1+1)=20 minus 10*10+10*5+20*3 = 100+50+60 = 210 -> clamped to 0
    assertEquals(0, $result['resources']['energy']);
});

test('Storage capacity caps resources', function (): void {
    $state = [
        'buildings' => ['metal_mine' => 20, 'crystal_mine' => 0, 'deuterium_synthesizer' => 0, 'solar_plant' => 0, 'metal_storage' => 1],
        'resources' => ['metal' => 100_000_000, 'crystal' => 0, 'deuterium' => 0, 'energy' => 0],
        'last_resource_update' => date('Y-m-d H:i:s', time() - 3600),
    ];
    $nowMs = (int) floor(microtime(true) * 1000);
    $result = ResourceEngine::produce($state, $nowMs);
    assertEquals(ResourceEngine::storageCapacity(1), $result['resources']['metal'], 'metal capped by storage');
    assertEquals(150_000, ResourceEngine::storageCapacity(1), '100000 + 50000*(ceil(1.6^1)-1) = 100000 + 50000 = 150000');
    assertEquals(200_000, ResourceEngine::storageCapacity(2), '100000 + 50000*(ceil(1.6^2)-1) = 100000 + 100000 = 200000');
    assertEquals(100_000, ResourceEngine::storageCapacity(0), 'no storage building keeps base 100k');
});

test('Elapsed time under threshold is ignored', function (): void {
    $state = [
        'buildings' => ['metal_mine' => 10],
        'resources' => ['metal' => 50, 'crystal' => 0, 'deuterium' => 0, 'energy' => 0],
        'last_resource_update' => date('Y-m-d H:i:s', time() - 1),
    ];
    $nowMs = (int) floor(microtime(true) * 1000);
    $result = ResourceEngine::produce($state, $nowMs);
    assertEquals(50, $result['resources']['metal'], 'no production under 3s threshold');
});

// --- Turns ------------------------------------------------------------------

test('Turn generation accumulates turns based on elapsed time', function (): void {
    $now = 1_000_000_000_000; // fixed ms
    $turnsData = ['availableTurns' => 5, 'streakTurns' => 0, 'lastTurnTimestamp' => $now - 15 * 60_000]; // 15 min
    $result = TurnEngine::generate($turnsData, $now);
    assertEquals(65, $result['availableTurns'], '5 + 15 minutes * 4 turns/min = 65');
    assertEquals(60, $result['streakTurns']);
});

test('Turn generation applies streak bonus after 10+ streak', function (): void {
    $now = 1_000_000_000_000;
    $turnsData = ['availableTurns' => 0, 'streakTurns' => 15, 'lastTurnTimestamp' => $now - 60_000]; // 1 min
    $result = TurnEngine::generate($turnsData, $now);
    // 4 new turns + 10% bonus floor(4*0.1)=0
    assertEquals(4, $result['availableTurns']);
    assertEquals(19, $result['streakTurns']);
});

test('Offline turns are capped', function (): void {
    $now = 1_000_000_000_000;
    $turnsData = ['availableTurns' => 0, 'streakTurns' => 0, 'lastTurnTimestamp' => $now - 10 * 86400 * 1000]; // 10 days
    $result = TurnEngine::generate($turnsData, $now);
    assertTrue($result['availableTurns'] <= 8640, 'capped at max offline turns');
    assertEquals(8640, $result['availableTurns']);
});

// --- Fleet ------------------------------------------------------------------

test('Travel time grows with distance and shrinks with speed', function (): void {
    $slow = FleetEngine::travelTime([1, 1, 1], [9, 10, 10], 1000);
    $fast = FleetEngine::travelTime([1, 1, 1], [9, 10, 10], 100000);
    assertTrue($fast < $slow, 'faster ship arrives sooner');

    $near = FleetEngine::travelTime([1, 1, 1], [1, 1, 2], 5000);
    $far = FleetEngine::travelTime([1, 1, 1], [1, 10, 10], 5000);
    assertTrue($near < $far, 'further distance takes longer');
});

test('Coordinate parsing', function (): void {
    assertEquals([1, 2, 3], FleetEngine::parseCoordinates('[1:2:3]'));
    assertEquals(null, FleetEngine::parseCoordinates('nope'));
});

test('Fleet speed uses the slowest ship', function (): void {
    $speed = FleetEngine::fleetSpeed(['light_fighter' => 2, 'espionage_probe' => 1]);
    assertEquals(12500, $speed, 'probe is 100M but LF is 12500, min = 12500');
});

test('Cargo capacity sums all ships', function (): void {
    $cargo = FleetEngine::cargoCapacity(['small_cargo' => 2, 'large_cargo' => 1]);
    assertEquals(2 * 5000 + 25000, $cargo);
});
