<?php

declare(strict_types=1);

/**
 * Ship unit definitions and combat stats.
 *
 * Port of shared/config/ships.ts (UNIT_PARAMS, RAPID_FIRE, costs) to PHP arrays.
 * Stats are per-unit: structuralIntegrity (hull), shield, attack, speed,
 * capacity (cargo), fuelConsumption.
 */

return [
    'UNIT_PARAMS' => [
        // --- Small / fast ships ---
        'small_cargo' => [
            'id' => 'small_cargo', 'class' => 'cargo', 'category' => 'combat',
            'name' => 'Small Cargo',
            'structuralIntegrity' => 4000, 'shield' => 10, 'attack' => 5,
            'speed' => 5000, 'capacity' => 5000, 'fuelConsumption' => 10,
            'cost' => ['metal' => 2000, 'crystal' => 2000, 'deuterium' => 0],
            'build_time' => 4, 'rapid_fire' => [],
        ],
        'large_cargo' => [
            'id' => 'large_cargo', 'class' => 'cargo', 'category' => 'combat',
            'name' => 'Large Cargo',
            'structuralIntegrity' => 12000, 'shield' => 25, 'attack' => 5,
            'speed' => 7500, 'capacity' => 25000, 'fuelConsumption' => 50,
            'cost' => ['metal' => 6000, 'crystal' => 6000, 'deuterium' => 0],
            'build_time' => 8, 'rapid_fire' => [],
        ],
        'light_fighter' => [
            'id' => 'light_fighter', 'class' => 'fighter', 'category' => 'combat',
            'name' => 'Light Fighter',
            'structuralIntegrity' => 4000, 'shield' => 10, 'attack' => 50,
            'speed' => 12500, 'capacity' => 50, 'fuelConsumption' => 20,
            'cost' => ['metal' => 3000, 'crystal' => 1000, 'deuterium' => 0],
            'build_time' => 4, 'rapid_fire' => ['espionage_probe' => 5, 'solar_satellite' => 5],
        ],
        'heavy_fighter' => [
            'id' => 'heavy_fighter', 'class' => 'fighter', 'category' => 'combat',
            'name' => 'Heavy Fighter',
            'structuralIntegrity' => 10000, 'shield' => 25, 'attack' => 150,
            'speed' => 10000, 'capacity' => 100, 'fuelConsumption' => 75,
            'cost' => ['metal' => 6000, 'crystal' => 4000, 'deuterium' => 0],
            'build_time' => 8, 'rapid_fire' => ['espionage_probe' => 5, 'solar_satellite' => 5, 'small_cargo' => 3],
        ],
        'cruiser' => [
            'id' => 'cruiser', 'class' => 'cruiser', 'category' => 'combat',
            'name' => 'Cruiser',
            'structuralIntegrity' => 27000, 'shield' => 50, 'attack' => 400,
            'speed' => 15000, 'capacity' => 800, 'fuelConsumption' => 300,
            'cost' => ['metal' => 20000, 'crystal' => 7000, 'deuterium' => 2000],
            'build_time' => 16, 'rapid_fire' => ['light_fighter' => 6, 'espionage_probe' => 5, 'solar_satellite' => 5],
        ],
        'battleship' => [
            'id' => 'battleship', 'class' => 'battleship', 'category' => 'combat',
            'name' => 'Battleship',
            'structuralIntegrity' => 60000, 'shield' => 200, 'attack' => 1000,
            'speed' => 10000, 'capacity' => 1500, 'fuelConsumption' => 500,
            'cost' => ['metal' => 45000, 'crystal' => 15000, 'deuterium' => 0],
            'build_time' => 24, 'rapid_fire' => ['espionage_probe' => 5, 'solar_satellite' => 5, 'cargo_ship' => 3],
        ],
        'battlecruiser' => [
            'id' => 'battlecruiser', 'class' => 'battleship', 'category' => 'combat',
            'name' => 'Battlecruiser',
            'structuralIntegrity' => 70000, 'shield' => 400, 'attack' => 700,
            'speed' => 10000, 'capacity' => 750, 'fuelConsumption' => 250,
            'cost' => ['metal' => 30000, 'crystal' => 40000, 'deuterium' => 15000],
            'build_time' => 24, 'rapid_fire' => ['battleship' => 7, 'cruiser' => 3, 'heavy_fighter' => 4, 'espionage_probe' => 5, 'solar_satellite' => 5],
        ],
        'bomber' => [
            'id' => 'bomber', 'class' => 'bomber', 'category' => 'combat',
            'name' => 'Bomber',
            'structuralIntegrity' => 75000, 'shield' => 500, 'attack' => 1000,
            'speed' => 4000, 'capacity' => 500, 'fuelConsumption' => 1000,
            'cost' => ['metal' => 50000, 'crystal' => 25000, 'deuterium' => 15000],
            'build_time' => 32, 'rapid_fire' => ['plasma_turret' => 20, 'light_laser' => 10, 'heavy_laser' => 5],
        ],
        'destroyer' => [
            'id' => 'destroyer', 'class' => 'destroyer', 'category' => 'combat',
            'name' => 'Destroyer',
            'structuralIntegrity' => 110000, 'shield' => 500, 'attack' => 2000,
            'speed' => 5000, 'capacity' => 2000, 'fuelConsumption' => 1000,
            'cost' => ['metal' => 60000, 'crystal' => 50000, 'deuterium' => 15000],
            'build_time' => 32, 'rapid_fire' => ['battlecruiser' => 2, 'battleship' => 5, 'espionage_probe' => 5, 'solar_satellite' => 5],
        ],
        'death_star' => [
            'id' => 'death_star', 'class' => 'deathstar', 'category' => 'combat',
            'name' => 'Death Star',
            'structuralIntegrity' => 9_000_000, 'shield' => 50000, 'attack' => 200000,
            'speed' => 100, 'capacity' => 1000000, 'fuelConsumption' => 1,
            'cost' => ['metal' => 5_000_000, 'crystal' => 4_000_000, 'deuterium' => 1_000_000],
            'build_time' => 48, 'rapid_fire' => ['rocket_launcher' => 200, 'light_laser' => 200, 'heavy_laser' => 100, 'ion_cannon' => 100, 'gauss_cannon' => 50, 'small_cargo' => 250, 'large_cargo' => 250, 'light_fighter' => 200, 'heavy_fighter' => 100, 'cruiser' => 33, 'battleship' => 30, 'battlecruiser' => 15, 'bomber' => 25, 'destroyer' => 5],
        ],
        // --- Support ships ---
        'recycler' => [
            'id' => 'recycler', 'class' => 'recycler', 'category' => 'combat',
            'name' => 'Recycler',
            'structuralIntegrity' => 16000, 'shield' => 10, 'attack' => 1,
            'speed' => 2000, 'capacity' => 20000, 'fuelConsumption' => 300,
            'cost' => ['metal' => 10000, 'crystal' => 6000, 'deuterium' => 2000],
            'build_time' => 8, 'rapid_fire' => [],
        ],
        'espionage_probe' => [
            'id' => 'espionage_probe', 'class' => 'probe', 'category' => 'combat',
            'name' => 'Espionage Probe',
            'structuralIntegrity' => 1000, 'shield' => 0, 'attack' => 0,
            'speed' => 100000000, 'capacity' => 5, 'fuelConsumption' => 1,
            'cost' => ['metal' => 0, 'crystal' => 1000, 'deuterium' => 0],
            'build_time' => 1, 'rapid_fire' => [],
        ],
        'solar_satellite' => [
            'id' => 'solar_satellite', 'class' => 'satellite', 'category' => 'combat',
            'name' => 'Solar Satellite',
            'structuralIntegrity' => 2000, 'shield' => 0, 'attack' => 0,
            'speed' => 0, 'capacity' => 0, 'fuelConsumption' => 0,
            'cost' => ['metal' => 0, 'crystal' => 2000, 'deuterium' => 500],
            'build_time' => 1, 'rapid_fire' => [],
        ],
        'colony_ship' => [
            'id' => 'colony_ship', 'class' => 'colony', 'category' => 'combat',
            'name' => 'Colony Ship',
            'structuralIntegrity' => 30000, 'shield' => 100, 'attack' => 50,
            'speed' => 2500, 'capacity' => 7500, 'fuelConsumption' => 1000,
            'cost' => ['metal' => 10000, 'crystal' => 20000, 'deuterium' => 10000],
            'build_time' => 16, 'rapid_fire' => [],
        ],
        'recycler_ship' => [
            'id' => 'recycler_ship', 'class' => 'recycler', 'category' => 'combat',
            'name' => 'Recycler Ship',
            'structuralIntegrity' => 16000, 'shield' => 10, 'attack' => 1,
            'speed' => 2000, 'capacity' => 20000, 'fuelConsumption' => 300,
            'cost' => ['metal' => 10000, 'crystal' => 6000, 'deuterium' => 2000],
            'build_time' => 8, 'rapid_fire' => [],
        ],
    ],

    // Rapid fire keyed by attacker -> defender
    'RAPID_FIRE' => [
        'light_fighter' => ['espionage_probe' => 5, 'solar_satellite' => 5],
        'heavy_fighter' => ['espionage_probe' => 5, 'solar_satellite' => 5, 'small_cargo' => 3],
        'cruiser' => ['light_fighter' => 6, 'espionage_probe' => 5, 'solar_satellite' => 5],
        'battleship' => ['espionage_probe' => 5, 'solar_satellite' => 5, 'cargo_ship' => 3],
        'battlecruiser' => ['battleship' => 7, 'cruiser' => 3, 'heavy_fighter' => 4, 'espionage_probe' => 5, 'solar_satellite' => 5],
        'bomber' => ['plasma_turret' => 20, 'light_laser' => 10, 'heavy_laser' => 5],
        'destroyer' => ['battlecruiser' => 2, 'battleship' => 5, 'espionage_probe' => 5, 'solar_satellite' => 5],
        'death_star' => ['rocket_launcher' => 200, 'light_laser' => 200, 'heavy_laser' => 100, 'ion_cannon' => 100, 'gauss_cannon' => 50, 'small_cargo' => 250, 'large_cargo' => 250, 'light_fighter' => 200, 'heavy_fighter' => 100, 'cruiser' => 33, 'battleship' => 30, 'battlecruiser' => 15, 'bomber' => 25, 'destroyer' => 5],
    ],

    'fleet_slots_per_computer_level' => 1,
];
