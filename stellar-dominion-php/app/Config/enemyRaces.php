<?php

declare(strict_types=1);

/**
 * Enemy race definitions for expedition combat encounters.
 *
 * Port of shared/config/enemyRaces.ts to PHP arrays.
 */

return [
    'enemy_races' => [
        'zerg' => [
            'id' => 'zerg',
            'name' => 'The Swarm',
            'description' => 'Endless tide of biological vessels.',
            'color' => '#84cc16',
            'ships' => ['light_fighter', 'heavy_fighter', 'cruiser'],
            'aggression' => 0.9,
            'power_scale' => 0.8,
        ],
        'protoss' => [
            'id' => 'protoss',
            'name' => 'The Enclave',
            'description' => 'Ancient psionic empire with powerful shields.',
            'color' => '#22d3ee',
            'ships' => ['battlecruiser', 'battleship', 'cruiser'],
            'aggression' => 0.5,
            'power_scale' => 1.4,
        ],
        'reavers' => [
            'id' => 'reavers',
            'name' => 'Reaver Corsairs',
            'description' => 'Pirate fleets preying on the frontier.',
            'color' => '#f97316',
            'ships' => ['light_fighter', 'battleship', 'battlecruiser'],
            'aggression' => 1.0,
            'power_scale' => 1.0,
        ],
        'ancient' => [
            'id' => 'ancient',
            'name' => 'The Ancients',
            'description' => 'Millennia-old guardian automatons.',
            'color' => '#a855f7',
            'ships' => ['destroyer', 'death_star', 'battleship'],
            'aggression' => 0.3,
            'power_scale' => 2.0,
        ],
    ],
];
