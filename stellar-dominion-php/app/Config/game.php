<?php

declare(strict_types=1);

/**
 * Global configuration for the Stellar Dominion PHP rework.
 *
 * Mirrors the original server/config/gameSettings.ts and startupConfig.ts,
 * translated to PHP array constants. Runtime-sensitive values can be
 * overridden via environment variables (see .env.example).
 */

return [
    'app' => [
        'name' => 'Stellar Dominion',
        'version' => '1.0.0-php',
        'build' => 'alpha',
        'timezone' => 'UTC',
    ],

    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'stellar_dominion',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    'auth' => [
        'session_ttl_seconds' => 7 * 24 * 3600,      // 1 week, matches express-session
        'cookie' => 'sd_session',
        'bcrypt_cost' => 12,
        // Legacy dev bypass (original DEV_AUTH_BYPASS equivalent)
        'dev_bypass_enabled' => (bool) (getenv('DEV_AUTH_BYPASS') ?: false),
    ],

    'security' => [
        'rate_limit_api_per_minute' => 120,
        'rate_limit_auth_per_15min' => 10,
        'password_min_length' => 8,
    ],

    'game' => [
        // Tick intervals in seconds (original defaults were 1000ms each)
        'tick' => [
            'resource_tick' => 1,
            'turn_tick' => 1,
            'construction_tick' => 1,
            'refinery_tick' => 1,
            'research_tick' => 5,
            'fleet_maintenance' => 10,
            'mission_processing' => 8,
            'expedition_tick' => 15,
            'market_tick' => 1,
            'alliance_treasury' => 300,
            'leaderboard_update' => 600,
        ],

        // Offline catch-up caps (mirror gameJobs.ts)
        'max_offline_production_hours' => 24,
        'max_offline_turns' => 8640,
        'turns_per_minute' => 4,

        // Starting resources: metal/crystal/deuterium/energy
        'start_resources' => ['metal' => 1000, 'crystal' => 500, 'deuterium' => 0, 'energy' => 0],

        // Construction queue cap
        'max_queue_items' => 5,

        // Progression scaling (shared/config/progressionSystem.ts)
        'progression' => [
            'level_multiplier' => 1.015,
            'tier_multiplier' => 1.08,
            'max_tier' => 99,
            'max_level' => 999,
            'levels_per_tier' => 10,
        ],

        // Combat (server/combatEngine.ts COMBAT_CONFIG)
        'combat' => [
            'max_rounds' => 100,
            'critical_chance_base' => 0.05,
            'critical_multiplier' => 1.5,
            'minimum_damage' => 1,
            'damage_variance' => 0.4,   // +/-20%
            'plunder_percent' => 0.30,  // 30% of defender resources
            'loss_divisor' => 100,      // aggregate casualties = damage / 100
        ],

        // Research bonuses per level (combatEngine RESEARCH_BONUSES)
        'research_bonuses' => [
            'weapons_tech' => ['attack' => 0.05],
            'shielding_tech' => ['defense' => 0.05],
            'armour_tech' => ['health' => 0.03],
            'combustion_drive' => ['speed' => 0.02],
        ],

        // Economy
        'economy' => [
            'bank_interest_rate' => 0.05,   // per interval, original bankAccounts default
            'bank_max_withdrawal' => 1_000_000,
            'bank_max_deposit' => 10_000_000,
            'market_fee_percent' => 0.05,
        ],
    ],

    'universe' => [
        'galaxies' => 9,
        'sectors_per_galaxy' => 10,
        'systems_per_sector' => 10,
        'max_planets_per_system' => 15,
    ],
];
