<?php

declare(strict_types=1);

/**
 * Technology definitions and research scaling.
 *
 * Port of shared/config/technologies.ts (TECHNOLOGY_LIST, base costs,
 * growth factors, prerequisites) to PHP arrays.
 */

return [
    // Numeric GIDs (classic OGame) keyed by technology id
    'GIDS' => [
        'energy_technology' => 113,
        'laser_technology' => 120,
        'ion_technology' => 121,
        'plasma_technology' => 122,
        'shield_technology' => 110,
        'armour_technology' => 111,
        'espionage_technology' => 106,
        'computer_technology' => 108,
        'astrophysics' => 124,
        'intergalactic_research' => 123,
        'weapons_technology' => 109,
        'combustion_drive' => 115,
        'impulse_drive' => 117,
        'hyperspace_drive' => 118,
        'graviton_technology' => 114,
    ],

    // Research branches (researchTree.ts)
    'branches' => [
        'combat' => ['weapons_technology', 'armour_technology', 'shield_technology', 'laser_technology', 'ion_technology', 'plasma_technology', 'graviton_technology'],
        'drives' => ['combustion_drive', 'impulse_drive', 'hyperspace_drive', 'intergalactic_research'],
        'economy' => ['energy_technology', 'astrophysics', 'espionage_technology', 'computer_technology'],
    ],

    'technologies' => [
        'energy_technology' => [
            'id' => 'energy_technology', 'gid' => 113, 'branch' => 'economy',
            'name' => 'Energy Technology', 'description' => 'Improves energy production.',
            'cost' => ['metal' => 0, 'crystal' => 800, 'deuterium' => 400],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => [],
        ],
        'laser_technology' => [
            'id' => 'laser_technology', 'gid' => 120, 'branch' => 'combat',
            'name' => 'Laser Technology', 'description' => 'Improves laser weapon power.',
            'cost' => ['metal' => 200, 'crystal' => 100, 'deuterium' => 0],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['energy_technology' => 2],
        ],
        'ion_technology' => [
            'id' => 'ion_technology', 'gid' => 121, 'branch' => 'combat',
            'name' => 'Ion Technology', 'description' => 'Improves ion weapon power.',
            'cost' => ['metal' => 1000, 'crystal' => 300, 'deuterium' => 100],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['laser_technology' => 5, 'energy_technology' => 4],
        ],
        'plasma_technology' => [
            'id' => 'plasma_technology', 'gid' => 122, 'branch' => 'combat',
            'name' => 'Plasma Technology', 'description' => 'Improves plasma weapon power.',
            'cost' => ['metal' => 2000, 'crystal' => 4000, 'deuterium' => 1000],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['ion_technology' => 5, 'laser_technology' => 10],
        ],
        'shield_technology' => [
            'id' => 'shield_technology', 'gid' => 110, 'branch' => 'combat',
            'name' => 'Shield Technology', 'description' => 'Improves shield power.',
            'cost' => ['metal' => 200, 'crystal' => 600, 'deuterium' => 0],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['energy_technology' => 3],
        ],
        'armour_technology' => [
            'id' => 'armour_technology', 'gid' => 111, 'branch' => 'combat',
            'name' => 'Armour Technology', 'description' => 'Improves hull strength.',
            'cost' => ['metal' => 1000, 'crystal' => 0, 'deuterium' => 0],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => [],
        ],
        'espionage_technology' => [
            'id' => 'espionage_technology', 'gid' => 106, 'branch' => 'economy',
            'name' => 'Espionage Technology', 'description' => 'Improves espionage and scanning.',
            'cost' => ['metal' => 200, 'crystal' => 1000, 'deuterium' => 200],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => [],
        ],
        'computer_technology' => [
            'id' => 'computer_technology', 'gid' => 108, 'branch' => 'economy',
            'name' => 'Computer Technology', 'description' => 'Improves fleet slot capacity.',
            'cost' => ['metal' => 0, 'crystal' => 400, 'deuterium' => 600],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => [],
        ],
        'astrophysics' => [
            'id' => 'astrophysics', 'gid' => 124, 'branch' => 'economy',
            'name' => 'Astrophysics', 'description' => 'Allows additional colonies. 1 per level.',
            'cost' => ['metal' => 400, 'crystal' => 800, 'deuterium' => 400],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['espionage_technology' => 4],
        ],
        'intergalactic_research' => [
            'id' => 'intergalactic_research', 'gid' => 123, 'branch' => 'drives',
            'name' => 'Intergalactic Research', 'description' => 'Allows research network.',
            'cost' => ['metal' => 240_000, 'crystal' => 400_000, 'deuterium' => 160_000],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['computer_technology' => 8, 'hyperspace_drive' => 8],
        ],
        'weapons_technology' => [
            'id' => 'weapons_technology', 'gid' => 109, 'branch' => 'combat',
            'name' => 'Weapons Technology', 'description' => 'Improves all weapon damage. +10% per level.',
            'cost' => ['metal' => 800, 'crystal' => 200, 'deuterium' => 0],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['energy_technology' => 1],
        ],
        'combustion_drive' => [
            'id' => 'combustion_drive', 'gid' => 115, 'branch' => 'drives',
            'name' => 'Combustion Drive', 'description' => 'Improves speed of small ships. +10% per level.',
            'cost' => ['metal' => 400, 'crystal' => 0, 'deuterium' => 600],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => [],
        ],
        'impulse_drive' => [
            'id' => 'impulse_drive', 'gid' => 117, 'branch' => 'drives',
            'name' => 'Impulse Drive', 'description' => 'Improves speed of medium ships. +20% per level.',
            'cost' => ['metal' => 2000, 'crystal' => 4000, 'deuterium' => 600],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['combustion_drive' => 5, 'energy_technology' => 1],
        ],
        'hyperspace_drive' => [
            'id' => 'hyperspace_drive', 'gid' => 118, 'branch' => 'drives',
            'name' => 'Hyperspace Drive', 'description' => 'Improves speed of large ships. +30% per level.',
            'cost' => ['metal' => 10_000, 'crystal' => 20_000, 'deuterium' => 6000],
            'growth' => 2.0, 'time_base' => 0.5, 'prerequisites' => ['impulse_drive' => 5, 'shield_technology' => 5],
        ],
        'graviton_technology' => [
            'id' => 'graviton_technology', 'gid' => 114, 'branch' => 'combat',
            'name' => 'Graviton Technology', 'description' => 'Unlocks Death Star construction.',
            'cost' => ['metal' => 0, 'crystal' => 0, 'deuterium' => 0],
            'growth' => 1.5, 'time_base' => 0.5, 'prerequisites' => ['energy_technology' => 12, 'plasma_technology' => 12],
        ],
    ],

    // Research time: (sum of cost) * time_base / (research lab level * research speed)
    'time_base' => 0.5,
];
