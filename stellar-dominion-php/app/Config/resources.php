<?php

declare(strict_types=1);

/**
 * Resource definitions and production formulas.
 *
 * Port of shared/config/resourceConfig.ts and shared/ogameMechanics.ts
 * calculateProduction(). The four primary resources use the classic OGame
 * GIDs (metal=700, crystal=701, deuterium=702, energy=703).
 */

return [
    'RESOURCE_GIDS' => [
        'metal' => 700,
        'crystal' => 701,
        'deuterium' => 702,
        'energy' => 703,
    ],

    'resources' => [
        'metal' => ['id' => 'metal', 'name' => 'Metal', 'category' => 'mining', 'gid' => 700],
        'crystal' => ['id' => 'crystal', 'name' => 'Crystal', 'category' => 'mining', 'gid' => 701],
        'deuterium' => ['id' => 'deuterium', 'name' => 'Deuterium', 'category' => 'production', 'gid' => 702],
        'energy' => ['id' => 'energy', 'name' => 'Energy', 'category' => 'production', 'gid' => 703],
        'rare_alloys' => ['id' => 'rare_alloys', 'name' => 'Rare Alloys', 'category' => 'exotic', 'gid' => null],
        'plasma' => ['id' => 'plasma', 'name' => 'Plasma', 'category' => 'exotic', 'gid' => null],
        'food' => ['id' => 'food', 'name' => 'Food', 'category' => 'sustenance', 'gid' => null],
        'water' => ['id' => 'water', 'name' => 'Water', 'category' => 'sustenance', 'gid' => null],
        'credits' => ['id' => 'credits', 'name' => 'Credits', 'category' => 'currency', 'gid' => null],
    ],

    // Base output per mine level (classic OGame v0.84 formulas)
    'production' => [
        'metal' => [
            'formula' => '30 * level * (1.1 ** level)',
            'energy_use' => '10 * level * (1.1 ** level)',
        ],
        'crystal' => [
            'formula' => '20 * level * (1.1 ** level)',
            'energy_use' => '10 * level * (1.1 ** level)',
        ],
        'deuterium' => [
            // Temperature-dependent: max(0, 1.28 - 0.002 * (temp + 40))
            'formula' => '10 * level * (1.1 ** level) * temp_factor',
            'energy_use' => '20 * level * (1.1 ** level)',
        ],
        'solar_plant' => [
            'formula' => '20 * level * (1.1 ** level)',
        ],
        'fusion_plant' => [
            // 30 * level * (1.05 + energyTech*0.01 + plasmaTech*0.01)^level
            'formula' => '30 * level * (factor ** level)',
            'deuterium_use' => '10 * level * (factor ** level)',
        ],
        'solar_satellite' => [
            'formula' => '((temp + 40) / 4 + 20) * count',
        ],
    ],

    // Storage: 100_000 + 50_000 * ceil(1.6^level - 1)
    'storage' => [
        'base_capacity' => 100_000,
        'per_level' => 50_000,
        'growth' => 1.6,
    ],
];
