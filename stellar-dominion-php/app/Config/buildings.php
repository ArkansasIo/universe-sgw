<?php

declare(strict_types=1);

/**
 * Building definitions (classic OGame GIDs + descriptive string keys).
 *
 * Port of shared/config/buildings.ts (BUILDING_LIST, INITIAL_COSTS,
 * GROWTH_FACTORS, DURATION_DIVISORS) to PHP arrays.
 */

return [
    // Classic numeric GIDs (kept for cross-compat with original data)
    'GIDS' => [
        'metal_mine' => 1,
        'crystal_mine' => 2,
        'deuterium_synthesizer' => 3,
        'solar_plant' => 4,
        'fusion_plant' => 12,
        'robotics_factory' => 14,
        'nanite_factory' => 15,
        'shipyard' => 21,
        'metal_storage' => 22,
        'crystal_storage' => 23,
        'deuterium_tank' => 24,
        'research_lab' => 31,
        'terraformer' => 33,
        'alliance_depot' => 34,
        'missile_silo' => 44,
        'lunar_base' => 41,
        'sensor_phalanx' => 42,
        'jump_gate' => 43,
        'solar_satellite' => 212,
        'shield_dome' => 501,
        'city_center' => 1000,
        'habitat' => 1001,
        'barracks' => 1002,
        'shipyard_ext' => 1003,
        'trade_center' => 1004,
        'embassy' => 1005,
    ],

    // Full building catalog (initial cost, growth factor, duration divisor)
    'buildings' => [
        'metal_mine' => [
            'id' => 'metal_mine', 'gid' => 1, 'category' => 'production',
            'name' => 'Metal Mine', 'description' => 'Extracts metal from the planet surface.',
            'cost' => ['metal' => 60, 'crystal' => 15, 'deuterium' => 0],
            'growth' => 1.5, 'duration' => 8,
        ],
        'crystal_mine' => [
            'id' => 'crystal_mine', 'gid' => 2, 'category' => 'production',
            'name' => 'Crystal Mine', 'description' => 'Extracts crystal from the planet crust.',
            'cost' => ['metal' => 48, 'crystal' => 24, 'deuterium' => 0],
            'growth' => 1.6, 'duration' => 8,
        ],
        'deuterium_synthesizer' => [
            'id' => 'deuterium_synthesizer', 'gid' => 3, 'category' => 'production',
            'name' => 'Deuterium Synthesizer', 'description' => 'Synthesizes deuterium from the atmosphere.',
            'cost' => ['metal' => 225, 'crystal' => 75, 'deuterium' => 0],
            'growth' => 1.5, 'duration' => 8,
        ],
        'solar_plant' => [
            'id' => 'solar_plant', 'gid' => 4, 'category' => 'production',
            'name' => 'Solar Plant', 'description' => 'Generates energy from the sun.',
            'cost' => ['metal' => 75, 'crystal' => 30, 'deuterium' => 0],
            'growth' => 1.5, 'duration' => 8,
        ],
        'fusion_plant' => [
            'id' => 'fusion_plant', 'gid' => 12, 'category' => 'production',
            'name' => 'Fusion Plant', 'description' => 'Generates large amounts of energy using deuterium.',
            'cost' => ['metal' => 900, 'crystal' => 360, 'deuterium' => 180],
            'growth' => 1.8, 'duration' => 30,
        ],
        'robotics_factory' => [
            'id' => 'robotics_factory', 'gid' => 14, 'category' => 'facility',
            'name' => 'Robotics Factory', 'description' => 'Reduces construction time of buildings.',
            'cost' => ['metal' => 400, 'crystal' => 120, 'deuterium' => 200],
            'growth' => 2.0, 'duration' => 20,
        ],
        'nanite_factory' => [
            'id' => 'nanite_factory', 'gid' => 15, 'category' => 'facility',
            'name' => 'Nanite Factory', 'description' => 'Massively reduces construction and fleet build time.',
            'cost' => ['metal' => 1_000_000, 'crystal' => 500_000, 'deuterium' => 100_000],
            'growth' => 2.0, 'duration' => 40,
        ],
        'shipyard' => [
            'id' => 'shipyard', 'gid' => 21, 'category' => 'facility',
            'name' => 'Shipyard', 'description' => 'Allows construction of ships and defenses.',
            'cost' => ['metal' => 400, 'crystal' => 200, 'deuterium' => 100],
            'growth' => 2.0, 'duration' => 20,
        ],
        'metal_storage' => [
            'id' => 'metal_storage', 'gid' => 22, 'category' => 'storage',
            'name' => 'Metal Storage', 'description' => 'Increases metal storage capacity.',
            'cost' => ['metal' => 2000, 'crystal' => 0, 'deuterium' => 0],
            'growth' => 2.0, 'duration' => 4,
        ],
        'crystal_storage' => [
            'id' => 'crystal_storage', 'gid' => 23, 'category' => 'storage',
            'name' => 'Crystal Storage', 'description' => 'Increases crystal storage capacity.',
            'cost' => ['metal' => 2000, 'crystal' => 1000, 'deuterium' => 0],
            'growth' => 2.0, 'duration' => 4,
        ],
        'deuterium_tank' => [
            'id' => 'deuterium_tank', 'gid' => 24, 'category' => 'storage',
            'name' => 'Deuterium Tank', 'description' => 'Increases deuterium storage capacity.',
            'cost' => ['metal' => 2000, 'crystal' => 2000, 'deuterium' => 0],
            'growth' => 2.0, 'duration' => 4,
        ],
        'research_lab' => [
            'id' => 'research_lab', 'gid' => 31, 'category' => 'facility',
            'name' => 'Research Lab', 'description' => 'Required for research.',
            'cost' => ['metal' => 200, 'crystal' => 400, 'deuterium' => 200],
            'growth' => 2.0, 'duration' => 20,
        ],
        'terraformer' => [
            'id' => 'terraformer', 'gid' => 33, 'category' => 'special',
            'name' => 'Terraformer', 'description' => 'Adds 5 fields per level.',
            'cost' => ['metal' => 0, 'crystal' => 50_000, 'deuterium' => 100_000],
            'growth' => 2.0, 'duration' => 100,
        ],
        'alliance_depot' => [
            'id' => 'alliance_depot', 'gid' => 34, 'category' => 'special',
            'name' => 'Alliance Depot', 'description' => 'Allows alliance members to deliver resources.',
            'cost' => ['metal' => 20_000, 'crystal' => 15_000, 'deuterium' => 500],
            'growth' => 2.0, 'duration' => 30,
        ],
        'missile_silo' => [
            'id' => 'missile_silo', 'gid' => 44, 'category' => 'defense',
            'name' => 'Missile Silo', 'description' => 'Houses missiles. 10 missiles per level.',
            'cost' => ['metal' => 20_000, 'crystal' => 20_000, 'deuterium' => 1000],
            'growth' => 2.0, 'duration' => 40,
        ],
        'lunar_base' => [
            'id' => 'lunar_base', 'gid' => 41, 'category' => 'moon',
            'name' => 'Lunar Base', 'description' => 'Main building on moons.',
            'cost' => ['metal' => 20_000, 'crystal' => 40_000, 'deuterium' => 20_000],
            'growth' => 2.0, 'duration' => 40,
        ],
        'sensor_phalanx' => [
            'id' => 'sensor_phalanx', 'gid' => 42, 'category' => 'moon',
            'name' => 'Sensor Phalanx', 'description' => 'Scans enemy fleets within range.',
            'cost' => ['metal' => 2_000_000, 'crystal' => 4_000_000, 'deuterium' => 2_000_000],
            'growth' => 2.0, 'duration' => 60,
        ],
        'jump_gate' => [
            'id' => 'jump_gate', 'gid' => 43, 'category' => 'moon',
            'name' => 'Jump Gate', 'description' => 'Instantly moves fleets between jump gates.',
            'cost' => ['metal' => 2_000_000, 'crystal' => 4_000_000, 'deuterium' => 2_000_000],
            'growth' => 2.0, 'duration' => 60,
        ],
        'solar_satellite' => [
            'id' => 'solar_satellite', 'gid' => 212, 'category' => 'production',
            'name' => 'Solar Satellite', 'description' => 'Generates energy based on planet temperature.',
            'cost' => ['metal' => 0, 'crystal' => 2000, 'deuterium' => 500],
            'growth' => 1.0, 'duration' => 4,
        ],
        'shield_dome' => [
            'id' => 'shield_dome', 'gid' => 501, 'category' => 'defense',
            'name' => 'Shield Dome', 'description' => 'Greatly increases planet defense.',
            'cost' => ['metal' => 10_000_000, 'crystal' => 10_000_000, 'deuterium' => 10_000_000],
            'growth' => 2.0, 'duration' => 80,
        ],
        'city_center' => [
            'id' => 'city_center', 'gid' => 1000, 'category' => 'city',
            'name' => 'City Center', 'description' => 'Hub of the colony city.',
            'cost' => ['metal' => 5000, 'crystal' => 5000, 'deuterium' => 2500],
            'growth' => 1.8, 'duration' => 40,
        ],
        'habitat' => [
            'id' => 'habitat', 'gid' => 1001, 'category' => 'city',
            'name' => 'Habitat', 'description' => 'Houses population. +500 population per level.',
            'cost' => ['metal' => 2000, 'crystal' => 3000, 'deuterium' => 1000],
            'growth' => 1.8, 'duration' => 25,
        ],
        'barracks' => [
            'id' => 'barracks', 'gid' => 1002, 'category' => 'military',
            'name' => 'Barracks', 'description' => 'Allows ground troops training.',
            'cost' => ['metal' => 3000, 'crystal' => 2000, 'deuterium' => 1000],
            'growth' => 2.0, 'duration' => 20,
        ],
        'shipyard_ext' => [
            'id' => 'shipyard_ext', 'gid' => 1003, 'category' => 'military',
            'name' => 'Extended Shipyard', 'description' => 'Allows construction of larger ship classes.',
            'cost' => ['metal' => 5000, 'crystal' => 3000, 'deuterium' => 1500],
            'growth' => 2.0, 'duration' => 30,
        ],
        'trade_center' => [
            'id' => 'trade_center', 'gid' => 1004, 'category' => 'economy',
            'name' => 'Trade Center', 'description' => 'Enables market trading and better rates.',
            'cost' => ['metal' => 5000, 'crystal' => 8000, 'deuterium' => 4000],
            'growth' => 1.8, 'duration' => 40,
        ],
        'embassy' => [
            'id' => 'embassy', 'gid' => 1005, 'category' => 'social',
            'name' => 'Embassy', 'description' => 'Allows joining/creating alliances.',
            'cost' => ['metal' => 20_000, 'crystal' => 10_000, 'deuterium' => 10_000],
            'growth' => 2.0, 'duration' => 30,
        ],
    ],

    // Construction time: (sum of costs in metal-equivalent) / (duration * speed)
    'time_energy_use' => [
        'metal_mine' => ['energy' => 10],
        'crystal_mine' => ['energy' => 10],
        'deuterium_synthesizer' => ['energy' => 20],
        'solar_plant' => ['energy' => 0],
        'fusion_plant' => ['deuterium' => 10],
    ],

    // Extra requirements keyed by building id
    'requirements' => [
        'fusion_plant' => ['energy_technology' => 3],
        'deuterium_synthesizer' => ['deuterium' => null],
    ],
];
