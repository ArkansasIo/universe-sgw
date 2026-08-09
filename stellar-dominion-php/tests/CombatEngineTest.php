<?php

declare(strict_types=1);

use StellarDominion\Engine\CombatEngine;

function battle(array $attackerUnits, array $defenderUnits, array $defResources = ['metal' => 100000, 'crystal' => 100000, 'deuterium' => 0], array $opts = []): array
{
    return CombatEngine::simulateBattle(
        [[
            'fleetMissionId' => 'att_fleet',
            'ownerId' => 'attacker',
            'units' => $attackerUnits,
            'weaponTech' => 0, 'shieldTech' => 0, 'armorTech' => 0,
        ]],
        [[
            'fleetMissionId' => 'def_fleet',
            'ownerId' => 'defender',
            'units' => $defenderUnits,
            'weaponTech' => 0, 'shieldTech' => 0, 'armorTech' => 0,
        ]],
        $defResources,
        $opts,
    );
}

test('Overwhelming force wins the battle', function (): void {
    $result = battle(
        [['battleship', 100]],
        [['light_fighter', 1]],
    );
    assertEquals('attacker', $result['winner']);
    assertTrue($result['roundCount'] >= 1 && $result['roundCount'] <= 6, 'round count within bounds');
    assertEquals(0, array_sum($result['defenderUnitsResult']), 'defender fleet destroyed');
    assertEquals(100, array_sum($result['attackerUnitsResult']), 'attacker fleet intact');
});

test('Defender wins against a hopeless attack', function (): void {
    $result = battle(
        [['light_fighter', 1]],
        [['rocket_launcher', 200]],
    );
    assertEquals('defender', $result['winner']);
    assertEquals(0, array_sum($result['attackerUnitsResult']), 'attacker wiped out');
});

test('Result includes per-fleet breakdown and losses', function (): void {
    $result = battle([['light_fighter', 50]], [['light_fighter', 50]]);
    assertTrue(isset($result['attackerUnitsStart']['light_fighter']));
    assertTrue(isset($result['attackerUnitsLost']));
    assertTrue(isset($result['defenderUnitsStart']));
    assertTrue(isset($result['defenderUnitsLost']));
    assertTrue(count($result['attackerFleetResults']) === 1);
    assertTrue(count($result['defenderFleetResults']) === 1);
    assertTrue(is_array($result['rounds']));
    assertEquals('att_fleet', $result['attackerFleetResults'][0]['fleetMissionId']);
});

test('Attacker loot is capped by cargo capacity', function (): void {
    $defResources = ['metal' => 1_000_000, 'crystal' => 1_000_000, 'deuterium' => 1_000_000];
    $result = battle(
        [['large_cargo', 2]],        // 2 * 25000 = 50000 cargo
        [['espionage_probe', 1]],    // no defense
        $defResources,
    );
    assertEquals('attacker', $result['winner']);
    $lootTotal = array_sum($result['loot']);
    assertTrue($lootTotal <= 50000, 'loot cannot exceed cargo capacity');
});

test('Loot with huge capacity equals percentage of resources', function (): void {
    $defResources = ['metal' => 100000, 'crystal' => 50000, 'deuterium' => 25000];
    $result = battle(
        [['large_cargo', 10]],       // 250000 cargo, plenty
        [['espionage_probe', 1]],
        $defResources,
        ['lootPercentage' => 50],
    );
    assertEquals(50000, $result['loot']['metal'], '50% of 100000 metal');
    assertEquals(25000, $result['loot']['crystal']);
    assertEquals(12500, $result['loot']['deuterium']);
});

test('Tech levels boost unit stats', function (): void {
    // 5 battleships vs 1 light fighter with 10 weapon tech on attacker
    $result = CombatEngine::simulateBattle(
        [[
            'fleetMissionId' => 'a',
            'ownerId' => 'attacker',
            'units' => [['battleship', 5]],
            'weaponTech' => 10, 'shieldTech' => 10, 'armorTech' => 10,
        ]],
        [[
            'fleetMissionId' => 'd',
            'ownerId' => 'defender',
            'units' => [['light_fighter', 1]],
            'weaponTech' => 0, 'shieldTech' => 0, 'armorTech' => 0,
        ]],
        ['metal' => 0, 'crystal' => 0, 'deuterium' => 0],
    );
    assertEquals('attacker', $result['winner']);
    assertEquals(10, $result['attackerWeaponLevel']);
});

test('Moon chance scales with debris size', function (): void {
    // A big mutual slugfest produces millions of debris -> chance capped at 20%
    $result = battle([['battleship', 1000]], [['battleship', 1000]]);
    assertTrue($result['moonChance'] >= 0 && $result['moonChance'] <= 20, 'moon chance within bounds');
    assertTrue($result['moonChance'] >= 20, 'large battle debris should hit the 20% cap');

    // A tiny skirmish produces almost no debris -> ~0% chance
    $small = battle([['battleship', 1]], [['light_fighter', 1]]);
    assertEquals(0, $small['moonChance']);
});

test('Damage variance stays within config bounds across a battle', function (): void {
    $result = battle([['battleship', 100]], [['light_fighter', 1]]);
    foreach ($result['rounds'] as $round) {
        assertTrue($round['fullStrengthAttacker'] >= 0, 'no negative damage totals');
    }
});
