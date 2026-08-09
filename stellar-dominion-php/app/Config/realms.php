<?php

declare(strict_types=1);

/**
 * Realm definitions.
 *
 * Port of shared/config/realms.ts. Realms are playable factions with a
 * defining resource bonus, ship affinity and starting perks.
 */

return [
    'realms' => [
        'terran' => [
            'id' => 'terran',
            'name' => 'Terran Federation',
            'description' => 'Humanity\'s home faction. Balanced economy with a slight metal production edge.',
            'color' => '#3b82f6',
            'resource_bonus' => ['metal' => 1.10, 'crystal' => 1.00, 'deuterium' => 1.00],
            'ship_affinity' => 'light_fighter',
            'start_bonus' => ['metal' => 50000, 'crystal' => 20000, 'deuterium' => 10000],
            'perks' => ['engineer_trained' => true],
        ],
        'solari' => [
            'id' => 'solari',
            'name' => 'Solaris Dominion',
            'description' => 'Solar collectors of the empire. Enhanced energy and solar satellite output.',
            'color' => '#f59e0b',
            'resource_bonus' => ['metal' => 1.00, 'crystal' => 1.00, 'deuterium' => 1.00],
            'energy_bonus' => 1.25,
            'ship_affinity' => 'solar_satellite',
            'start_bonus' => ['metal' => 30000, 'crystal' => 40000, 'deuterium' => 20000],
            'perks' => ['solar_technician' => true],
        ],
        'kryll' => [
            'id' => 'kryll',
            'name' => 'Kryll Collective',
            'description' => 'Crystalline hive-mind. Superior crystal yields and crystal-hungry fleet discounts.',
            'color' => '#10b981',
            'resource_bonus' => ['metal' => 1.00, 'crystal' => 1.25, 'deuterium' => 1.00],
            'ship_affinity' => 'cruiser',
            'start_bonus' => ['metal' => 30000, 'crystal' => 50000, 'deuterium' => 10000],
            'perks' => ['crystal_engineer' => true],
        ],
        'vaalk' => [
            'id' => 'vaalk',
            'name' => 'Vaalk Warhost',
            'description' => 'War-mad raiders. Increased combat damage and fleet attack bonuses.',
            'color' => '#ef4444',
            'resource_bonus' => ['metal' => 1.10, 'crystal' => 1.00, 'deuterium' => 1.00],
            'combat_bonus' => 1.10,
            'ship_affinity' => 'battleship',
            'start_bonus' => ['metal' => 50000, 'crystal' => 15000, 'deuterium' => 10000],
            'perks' => ['warlord' => true],
        ],
        'nexus' => [
            'id' => 'nexus',
            'name' => 'Nexus Syndicate',
            'description' => 'Interstellar traders. Faster ship speed and market fee reductions.',
            'color' => '#8b5cf6',
            'resource_bonus' => ['metal' => 1.00, 'crystal' => 1.00, 'deuterium' => 1.15],
            'ship_speed_bonus' => 1.15,
            'market_fee_reduction' => 0.5,
            'ship_affinity' => 'battlecruiser',
            'start_bonus' => ['metal' => 40000, 'crystal' => 30000, 'deuterium' => 20000],
            'perks' => ['merchant' => true],
        ],
    ],
];
