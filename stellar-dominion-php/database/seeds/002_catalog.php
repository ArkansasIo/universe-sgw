<?php

declare(strict_types=1);

/**
 * Seed research_technologies and system_settings from the PHP configs
 * (single source of truth lives in app/Config/*.php).
 *
 * Called by database/seed.php after the *.sql seed files.
 */

use StellarDominion\Core\UUID;

$db = Database::connection();

$techs = require $base . '/app/Config/technologies.php';
$insert = $db->prepare(
    'INSERT INTO research_technologies
       (id, tech_key, name, branch, description,
        base_cost_metal, base_cost_crystal, base_cost_deuterium, base_cost_energy,
        growth_factor, base_time_seconds, prerequisites, bonuses)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE name = VALUES(name)'
);

$count = 0;
foreach ($techs['technologies'] as $key => $tech) {
    $insert->execute([
        UUID::v5('research', $key),
        $key,
        $tech['name'],
        $tech['branch'],
        $tech['description'],
        $tech['cost']['metal'] ?? 0,
        $tech['cost']['crystal'] ?? 0,
        $tech['cost']['deuterium'] ?? 0,
        $tech['growth'],
        (int) round(($tech['cost']['metal'] + $tech['cost']['crystal'] + $tech['cost']['deuterium']) * $techs['time_base']),
        json_encode($tech['prerequisites']),
        json_encode($tech['bonuses'] ?? new stdClass()),
    ]);
    $count++;
}

echo "Seeded $count research technologies.\n";

// Default system settings
$settings = [
    ['game_version', json_encode('1.0.0-php'), 'Current game version', 'general'],
    ['tick_interval_ms', json_encode(1000), 'Interval between cron ticks', 'performance'],
    ['max_offline_production_hours', json_encode(24), 'Offline catch-up cap', 'gameplay'],
    ['turns_per_minute', json_encode(4), 'Turn regeneration rate', 'gameplay'],
    ['maintenance_mode', json_encode(false), 'Global maintenance flag', 'general'],
];

$insertSetting = $db->prepare(
    'INSERT INTO system_settings (id, setting_key, setting_value, description, category)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);
foreach ($settings as [$key, $value, $description, $category]) {
    $insertSetting->execute([UUID::v5('setting', $key), $key, $value, $description, $category]);
}
echo "Seeded " . count($settings) . " system settings.\n";
