<?php

declare(strict_types=1);

/**
 * Combat engine configuration.
 *
 * Port of shared/config/combatConfig.ts plus the derived constants used by
 * server/combatEngine.ts.
 */

return [
    // Number of fleet composition slots per player (fleet management)
    'fleet_slots' => 6,

    // Battle rounds before automatic draw
    'max_rounds' => 6,

    // Random damage factor per shot: attack * (factor_range/2 + rand * factor_range)
    // Classic OGame uses 0.7..1.3, i.e. +-30%.
    'damage_factor' => 0.3,

    // Shield regeneration after each round (% of shield value restored)
    'shield_regen_rate' => 0.1,

    // Percent of attacker's fleet that returns if attacker loses
    'retreat_chance' => 0.0,

    // Fleet debris fields: % of hull converted to metal/crystal debris
    'debris_metal' => 0.3,
    'debris_crystal' => 0.3,

    // FleetSave / phalanx detection
    'phalanx_range_per_level' => 1,

    // Missile combat values (used by missile_silo)
    'interplanetary_missile' => [
        'attack' => 12000,
        'cost' => ['metal' => 12500, 'crystal' => 2500, 'deuterium' => 10000],
    ],

    // Attack credit economy: how much of destroyed ship cost counts as raid gain
    'raid_credit_ratio' => 0.5,
];
