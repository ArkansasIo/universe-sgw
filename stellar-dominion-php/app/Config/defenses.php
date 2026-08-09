<?php

declare(strict_types=1);

/**
 * Defense unit definitions.
 *
 * Port of shared/config/defenses.ts to PHP arrays.
 * Same stat keys as ships: structuralIntegrity, shield, attack, speed,
 * capacity, fuelConsumption. Defenses are stationary (speed 0).
 */

return [
    'defenses' => [
        'rocket_launcher' => [
            'id' => 'rocket_launcher', 'category' => 'defense',
            'name' => 'Rocket Launcher',
            'structuralIntegrity' => 2000, 'shield' => 20, 'attack' => 80,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 2000, 'crystal' => 0, 'deuterium' => 0],
            'build_time' => 1,
        ],
        'light_laser' => [
            'id' => 'light_laser', 'category' => 'defense',
            'name' => 'Light Laser',
            'structuralIntegrity' => 2000, 'shield' => 25, 'attack' => 100,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 1500, 'crystal' => 500, 'deuterium' => 0],
            'build_time' => 2,
        ],
        'heavy_laser' => [
            'id' => 'heavy_laser', 'category' => 'defense',
            'name' => 'Heavy Laser',
            'structuralIntegrity' => 8000, 'shield' => 100, 'attack' => 250,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 6000, 'crystal' => 2000, 'deuterium' => 0],
            'build_time' => 3,
        ],
        'ion_cannon' => [
            'id' => 'ion_cannon', 'category' => 'defense',
            'name' => 'Ion Cannon',
            'structuralIntegrity' => 8000, 'shield' => 500, 'attack' => 150,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 2000, 'crystal' => 6000, 'deuterium' => 0],
            'build_time' => 4,
        ],
        'gauss_cannon' => [
            'id' => 'gauss_cannon', 'category' => 'defense',
            'name' => 'Gauss Cannon',
            'structuralIntegrity' => 35000, 'shield' => 200, 'attack' => 1100,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 20000, 'crystal' => 15000, 'deuterium' => 2000],
            'build_time' => 8,
        ],
        'plasma_turret' => [
            'id' => 'plasma_turret', 'category' => 'defense',
            'name' => 'Plasma Turret',
            'structuralIntegrity' => 100000, 'shield' => 300, 'attack' => 3000,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 50000, 'crystal' => 50000, 'deuterium' => 30000],
            'build_time' => 12,
        ],
        'small_shield_dome' => [
            'id' => 'small_shield_dome', 'category' => 'defense',
            'name' => 'Small Shield Dome',
            'structuralIntegrity' => 20000, 'shield' => 2000, 'attack' => 1,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 20000, 'crystal' => 20000, 'deuterium' => 0],
            'build_time' => 20,
        ],
        'large_shield_dome' => [
            'id' => 'large_shield_dome', 'category' => 'defense',
            'name' => 'Large Shield Dome',
            'structuralIntegrity' => 100000, 'shield' => 10000, 'attack' => 1,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 50000, 'crystal' => 50000, 'deuterium' => 0],
            'build_time' => 20,
        ],
    ],
];
