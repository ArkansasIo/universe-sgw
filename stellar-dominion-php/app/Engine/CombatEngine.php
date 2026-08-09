<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

/**
 * Round-based combat simulator.
 *
 * Faithful PHP port of server/combat/PhpBattleEngine.ts (simulateBattle).
 * Each ship is simulated as an individual BattleUnit with hull plating,
 * shields, rapid fire and chance-to-explode hull mechanics. Tech bonuses
 * (+5% per level) are applied to attack / shield / structural integrity.
 */
final class CombatEngine
{
    public const DEFAULT_LOOT_PERCENT = 50;
    public const DEFAULT_DEBRIS_FROM_SHIPS_PERCENT = 30;
    public const DEFAULT_DEBRIS_FROM_DEFENSE_PERCENT = 0;
    public const DEFAULT_MAX_MOON_CHANCE = 20;
    public const DEFAULT_DEFENSE_REPAIR_RATE = 70;
    public const DEFAULT_MAX_ROUNDS = 6;

    /**
     * @param array $attackers  [{fleetMissionId, ownerId, units:[[machineName,count],...], weaponTech, shieldTech, armorTech}]
     * @param array $defenders  same shape
     * @param array $defenderResources {metal,crystal,deuterium}
     */
    public static function simulateBattle(array $attackers, array $defenders, array $defenderResources, array $opts = []): array
    {
        $lootPct = $opts['lootPercentage'] ?? self::DEFAULT_LOOT_PERCENT;
        $debrisShipsPct = $opts['debrisFromShipsPercent'] ?? self::DEFAULT_DEBRIS_FROM_SHIPS_PERCENT;
        $debrisDefensePct = $opts['debrisFromDefensePercent'] ?? self::DEFAULT_DEBRIS_FROM_DEFENSE_PERCENT;
        $maxMoonChance = $opts['maxMoonChance'] ?? self::DEFAULT_MAX_MOON_CHANCE;
        $defenseRepairRate = $opts['defenseRepairRate'] ?? self::DEFAULT_DEFENSE_REPAIR_RATE;
        $maxRounds = $opts['maxRounds'] ?? self::DEFAULT_MAX_ROUNDS;

        $primaryAttacker = $attackers[0] ?? [];
        $primaryDefender = $defenders[0] ?? [];

        $attackerWeapon = (int) ($primaryAttacker['weaponTech'] ?? 0);
        $attackerShield = (int) ($primaryAttacker['shieldTech'] ?? 0);
        $attackerArmor = (int) ($primaryAttacker['armorTech'] ?? 0);
        $defenderWeapon = (int) ($primaryDefender['weaponTech'] ?? 0);
        $defenderShield = (int) ($primaryDefender['shieldTech'] ?? 0);
        $defenderArmor = (int) ($primaryDefender['armorTech'] ?? 0);

        // Start maps
        $attackerUnitsStartMap = [];
        $defenderUnitsStartMap = [];
        foreach ($attackers as $a) {
            foreach (($a['units'] ?? []) as [$name, $count]) {
                $attackerUnitsStartMap[$name] = ($attackerUnitsStartMap[$name] ?? 0) + $count;
            }
        }
        foreach ($defenders as $d) {
            foreach (($d['units'] ?? []) as [$name, $count]) {
                $defenderUnitsStartMap[$name] = ($defenderUnitsStartMap[$name] ?? 0) + $count;
            }
        }

        $attackerUnits = [];
        foreach ($attackers as $a) {
            foreach (self::fleetUnitsToBattleUnits($a['units'] ?? [], $a['fleetMissionId'] ?? 'attacker', $a['ownerId'] ?? 'a', $attackerWeapon, $attackerShield, $attackerArmor) as $unit) {
                $attackerUnits[] = $unit;
            }
        }

        $defenderUnits = [];
        foreach ($defenders as $d) {
            foreach (self::fleetUnitsToBattleUnits($d['units'] ?? [], $d['fleetMissionId'] ?? 'defender', $d['ownerId'] ?? 'd', $defenderWeapon, $defenderShield, $defenderArmor) as $unit) {
                $defenderUnits[] = $unit;
            }
        }

        $attackerFleetResults = [];
        foreach ($attackers as $a) {
            $attackerFleetResults[$a['fleetMissionId'] ?? 'attacker'] = [
                'fleetMissionId' => $a['fleetMissionId'] ?? 'attacker',
                'ownerId' => $a['ownerId'] ?? 'a',
                'unitsStart' => self::collectUnitsMap(self::fleetUnitsToBattleUnits($a['units'] ?? [], $a['fleetMissionId'] ?? 'attacker', $a['ownerId'] ?? 'a', $attackerWeapon, $attackerShield, $attackerArmor)),
                'unitsResult' => $attackerUnitsStartMap,
                'unitsLost' => [],
                'resourceLoss' => ['metal' => 0, 'crystal' => 0, 'deuterium' => 0],
                'completelyDestroyed' => false,
            ];
        }

        $defenderFleetResults = [];
        foreach ($defenders as $d) {
            $defenderFleetResults[$d['fleetMissionId'] ?? 'defender'] = [
                'fleetMissionId' => $d['fleetMissionId'] ?? 'defender',
                'ownerId' => $d['ownerId'] ?? 'd',
                'unitsStart' => self::collectUnitsMap(self::fleetUnitsToBattleUnits($d['units'] ?? [], $d['fleetMissionId'] ?? 'defender', $d['ownerId'] ?? 'd', $defenderWeapon, $defenderShield, $defenderArmor)),
                'unitsResult' => $defenderUnitsStartMap,
                'unitsLost' => [],
                'completelyDestroyed' => false,
            ];
        }

        $rounds = [];
        $roundNumber = 0;
        $attackerLossesTotal = [];
        $defenderLossesTotal = [];

        while ($roundNumber < $maxRounds && count($attackerUnits) > 0 && count($defenderUnits) > 0) {
            $roundNumber++;

            $round = [
                'roundNumber' => $roundNumber,
                'attackerLossesInRound' => [],
                'defenderLossesInRound' => [],
                'attackerLosses' => [],
                'defenderLosses' => [],
                'attackerShips' => self::collectUnitsMap($attackerUnits),
                'defenderShips' => self::collectUnitsMap($defenderUnits),
                'hitsAttacker' => 0,
                'fullStrengthAttacker' => 0,
                'absorbedDamageDefender' => 0,
                'hitsDefender' => 0,
                'fullStrengthDefender' => 0,
                'absorbedDamageAttacker' => 0,
                'attackerLossesPerFleet' => [],
                'defenderLossesPerFleet' => [],
                'hitsPerAttackerFleet' => [],
                'damagePerAttackerFleet' => [],
            ];

            // Attackers fire at defenders
            foreach ($attackerUnits as $unit) {
                $canAttackAgain = true;
                while ($canAttackAgain && count($defenderUnits) > 0) {
                    $target = &$defenderUnits[random_int(0, count($defenderUnits) - 1)];
                    [$damage, $absorbed] = self::applyDamage($unit, $target);

                    if ($damage < 0) {
                        $canAttackAgain = false;
                        break;
                    }

                    $round['hitsAttacker']++;
                    $round['fullStrengthAttacker'] += $damage;
                    $round['absorbedDamageDefender'] += $absorbed;

                    $fid = $unit['fleetMissionId'] ?? 'attacker';
                    $round['hitsPerAttackerFleet'][$fid] = ($round['hitsPerAttackerFleet'][$fid] ?? 0) + 1;
                    $round['damagePerAttackerFleet'][$fid] = ($round['damagePerAttackerFleet'][$fid] ?? 0) + $damage;

                    $canAttackAgain = self::didSuccessfulRapidfire($unit, $target['config']['machineName'] ?? '');
                }
                unset($target);
            }

            // Defenders fire at attackers
            foreach ($defenderUnits as $unit) {
                $canAttackAgain = true;
                while ($canAttackAgain && count($attackerUnits) > 0) {
                    $target = &$attackerUnits[random_int(0, count($attackerUnits) - 1)];
                    [$damage, $absorbed] = self::applyDamage($unit, $target);

                    if ($damage < 0) {
                        $canAttackAgain = false;
                        break;
                    }

                    $round['hitsDefender']++;
                    $round['fullStrengthDefender'] += $damage;
                    $round['absorbedDamageAttacker'] += $absorbed;

                    $fid = $target['fleetMissionId'] ?? 'attacker';
                    $round['hitsPerAttackerFleet'][$fid] = ($round['hitsPerAttackerFleet'][$fid] ?? 0) + 1;
                    $round['damagePerAttackerFleet'][$fid] = ($round['damagePerAttackerFleet'][$fid] ?? 0) + $damage;

                    $canAttackAgain = self::didSuccessfulRapidfire($unit, $target['config']['machineName'] ?? '');
                }
                unset($target);
            }

            // Cleanup round: destroy ships, restore shields
            $newAttackerUnits = [];
            foreach ($attackerUnits as $unit) {
                if ($unit['currentHullPlating'] <= 0) {
                    $name = $unit['config']['machineName'] ?? '';
                    $round['attackerLossesInRound'][$name] = ($round['attackerLossesInRound'][$name] ?? 0) + 1;
                    $fid = $unit['fleetMissionId'] ?? 'attacker';
                    $round['attackerLossesPerFleet'][$fid][$name] = ($round['attackerLossesPerFleet'][$fid][$name] ?? 0) + 1;
                } else {
                    $unit['currentShieldPoints'] = $unit['originalShieldPoints'];
                    $newAttackerUnits[] = $unit;
                }
            }
            $attackerUnits = $newAttackerUnits;

            $newDefenderUnits = [];
            foreach ($defenderUnits as $unit) {
                if ($unit['currentHullPlating'] <= 0) {
                    $name = $unit['config']['machineName'] ?? '';
                    $round['defenderLossesInRound'][$name] = ($round['defenderLossesInRound'][$name] ?? 0) + 1;
                } else {
                    $unit['currentShieldPoints'] = $unit['originalShieldPoints'];
                    $newDefenderUnits[] = $unit;
                }
            }
            $defenderUnits = $newDefenderUnits;

            foreach ($round['attackerLossesInRound'] as $name => $count) {
                $attackerLossesTotal[$name] = ($attackerLossesTotal[$name] ?? 0) + $count;
            }
            foreach ($round['defenderLossesInRound'] as $name => $count) {
                $defenderLossesTotal[$name] = ($defenderLossesTotal[$name] ?? 0) + $count;
            }

            $round['attackerLosses'] = $attackerLossesTotal;
            $round['defenderLosses'] = $defenderLossesTotal;
            $round['attackerShips'] = self::collectUnitsMap($attackerUnits);
            $round['defenderShips'] = self::collectUnitsMap($defenderUnits);

            $rounds[] = $round;
        }

        $attackerUnitsResultMap = self::collectUnitsMap($attackerUnits);
        $defenderUnitsResultMap = self::collectUnitsMap($defenderUnits);

        // Per-fleet results
        foreach ($attackerFleetResults as &$fr) {
            $fr['unitsResult'] = [];
            foreach ($attackerUnits as $unit) {
                if ($unit['fleetMissionId'] === $fr['fleetMissionId']) {
                    $name = $unit['config']['machineName'] ?? '';
                    $fr['unitsResult'][$name] = ($fr['unitsResult'][$name] ?? 0) + 1;
                }
            }
            $fr['unitsLost'] = $fr['unitsStart'];
            foreach ($fr['unitsResult'] as $name => $count) {
                $fr['unitsLost'][$name] = max(0, ($fr['unitsLost'][$name] ?? 0) - $count);
            }
            $fr['completelyDestroyed'] = array_sum($fr['unitsResult']) === 0;
            $fr['resourceLoss'] = self::calculateResourceLoss($fr['unitsLost']);
        }
        unset($fr);

        foreach ($defenderFleetResults as &$fr) {
            $fr['unitsResult'] = [];
            foreach ($defenderUnits as $unit) {
                if ($unit['fleetMissionId'] === $fr['fleetMissionId']) {
                    $name = $unit['config']['machineName'] ?? '';
                    $fr['unitsResult'][$name] = ($fr['unitsResult'][$name] ?? 0) + 1;
                }
            }
            $fr['unitsLost'] = $fr['unitsStart'];
            foreach ($fr['unitsResult'] as $name => $count) {
                $fr['unitsLost'][$name] = max(0, ($fr['unitsLost'][$name] ?? 0) - $count);
            }
            $fr['completelyDestroyed'] = array_sum($fr['unitsResult']) === 0;
        }
        unset($fr);

        $attackerUnitsLost = [];
        foreach ($attackerUnitsStartMap as $name => $count) {
            $remaining = $attackerUnitsResultMap[$name] ?? 0;
            $attackerUnitsLost[$name] = max(0, $count - $remaining);
        }
        $defenderUnitsLost = [];
        foreach ($defenderUnitsStartMap as $name => $count) {
            $remaining = $defenderUnitsResultMap[$name] ?? 0;
            $defenderUnitsLost[$name] = max(0, $count - $remaining);
        }

        $attackerResourceLoss = self::calculateResourceLoss($attackerUnitsLost);
        $defenderResourceLoss = self::calculateResourceLoss($defenderUnitsLost);

        $repairedDefenses = self::calculateRepairedDefenses($defenderUnitsLost, $defenseRepairRate);

        $permanentlyLost = $defenderUnitsLost;
        foreach ($repairedDefenses as $name => $count) {
            $permanentlyLost[$name] = max(0, ($permanentlyLost[$name] ?? 0) - $count);
        }

        $debris = self::calculateDebris($attackerUnitsLost, $permanentlyLost, $debrisShipsPct, $debrisDefensePct);
        $wreckField = self::calculateWreckField($defenderUnitsLost, $defenderUnitsStartMap);

        $moonChance = 0;
        $moonCreated = false;
        if (($opts['defenderHasMoon'] ?? false) !== true) {
            $moonChance = self::calculateMoonChance($debris, $maxMoonChance);
            $moonCreated = self::rollMoonCreation($moonChance);
        }

        $winner = 'draw';
        if (count($defenderUnits) === 0 && count($attackerUnits) > 0) {
            $winner = 'attacker';
        } elseif (count($attackerUnits) === 0 && count($defenderUnits) > 0) {
            $winner = 'defender';
        }

        $loot = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        if ($winner === 'attacker') {
            $totalCargo = self::calculateTotalCargo($attackerUnits);
            $loot = self::calculateLoot($defenderResources, $lootPct, $totalCargo);
        }

        return [
            'winner' => $winner,
            'attackerUnitsStart' => $attackerUnitsStartMap,
            'attackerUnitsResult' => $attackerUnitsResultMap,
            'attackerUnitsLost' => $attackerUnitsLost,
            'attackerResourceLoss' => $attackerResourceLoss,
            'defenderUnitsStart' => $defenderUnitsStartMap,
            'defenderUnitsResult' => $defenderUnitsResultMap,
            'defenderUnitsLost' => $defenderUnitsLost,
            'defenderResourceLoss' => $defenderResourceLoss,
            'loot' => $loot,
            'debris' => $debris,
            'wreckField' => $wreckField,
            'moonChance' => $moonChance,
            'moonCreated' => $moonCreated,
            'moonExisted' => (bool) ($opts['defenderHasMoon'] ?? false),
            'repairedDefenses' => $repairedDefenses,
            'attackerWeaponLevel' => $attackerWeapon,
            'attackerShieldLevel' => $attackerShield,
            'attackerArmorLevel' => $attackerArmor,
            'defenderWeaponLevel' => $defenderWeapon,
            'defenderShieldLevel' => $defenderShield,
            'defenderArmorLevel' => $defenderArmor,
            'lootPercentage' => $lootPct,
            'rounds' => $rounds,
            'roundCount' => count($rounds),
            'attackerFleetResults' => array_values($attackerFleetResults),
            'defenderFleetResults' => array_values($defenderFleetResults),
        ];
    }

    // ------------------------------------------------------------------
    // Unit helpers (BattleUnit equivalents)
    // ------------------------------------------------------------------

    /** @param array $unit reference to the mutable unit array */
    private static function applyDamage(array &$unit, array &$target): array
    {
        $damage = $unit['attackPower'];
        $absorbed = 0;

        if ($damage < 0.01 * $target['originalShieldPoints']) {
            return [-1, 0]; // cannot pierce shield, stop firing
        }

        if ($target['currentShieldPoints'] > 0) {
            if ($damage <= $target['currentShieldPoints']) {
                $absorbed = $damage;
                $target['currentShieldPoints'] -= $damage;
            } else {
                $absorbed = $target['currentShieldPoints'];
                $target['currentHullPlating'] -= ($damage - $target['currentShieldPoints']);
                $target['currentShieldPoints'] = 0;
            }
        } else {
            $target['currentHullPlating'] -= $damage;
        }

        if (self::damagedHullExplosion($target)) {
            $target['currentShieldPoints'] = 0;
            $target['currentHullPlating'] = 0;
        }

        return [$damage, $absorbed];
    }

    private static function damagedHullExplosion(array $unit): bool
    {
        $hullPct = $unit['currentHullPlating'] / $unit['originalHullPlating'];
        if ($hullPct >= 0.7) {
            return false;
        }
        $explosionChance = (1 - $hullPct) * 100;
        return mt_rand(0, 100000) / 1000 < $explosionChance;
    }

    private static function didSuccessfulRapidfire(array $unit, string $defenderMachineName): bool
    {
        $rf = $unit['config']['rapidfire'] ?? [];
        $amount = $rf[$defenderMachineName] ?? 0;
        if ($amount <= 0) {
            return false;
        }
        $chance = 100 - floor((100 / $amount) * 100) / 100;
        return mt_rand(0, 100000) / 1000 < $chance;
    }

    /** Build battle units with tech bonuses (+5%/level, mirroring fleets.ts). */
    private static function fleetUnitsToBattleUnits(array $units, string $fleetMissionId, string $ownerId, int $weaponTech, int $shieldTech, int $armorTech): array
    {
        $result = [];
        foreach ($units as [$machineName, $count]) {
            $config = GameData::unit($machineName);
            if ($config === null) {
                continue;
            }
            $struct = self::applyTechBonus($config['structuralIntegrity'], $armorTech, 0.05);
            $shield = self::applyTechBonus($config['shield'], $shieldTech, 0.05);
            $attack = self::applyTechBonus($config['attack'], $weaponTech, 0.05);
            $template = [
                'config' => $config,
                'fleetMissionId' => $fleetMissionId,
                'ownerId' => $ownerId,
                'structuralIntegrity' => $struct,
                'originalHullPlating' => (int) floor($struct / 10),
                'currentHullPlating' => (int) floor($struct / 10),
                'originalShieldPoints' => $shield,
                'currentShieldPoints' => $shield,
                'attackPower' => $attack,
            ];
            for ($i = 0; $i < $count; $i++) {
                $result[] = $template;
            }
        }
        return $result;
    }

    private static function applyTechBonus(int|float $base, int $techLevel, float $bonusPerLevel): int
    {
        return (int) floor($base * (1 + $techLevel * $bonusPerLevel));
    }

    private static function collectUnitsMap(array $units): array
    {
        $map = [];
        foreach ($units as $u) {
            $name = $u['config']['machineName'] ?? '';
            $map[$name] = ($map[$name] ?? 0) + 1;
        }
        return $map;
    }

    // ------------------------------------------------------------------
    // Result helpers
    // ------------------------------------------------------------------

    private static function calculateResourceLoss(array $units): array
    {
        $metal = 0; $crystal = 0; $deuterium = 0;
        foreach ($units as $name => $count) {
            $stats = GameData::unit((string) $name);
            if ($stats) {
                $metal += $stats['metalCost'] * $count;
                $crystal += $stats['crystalCost'] * $count;
                $deuterium += $stats['deuteriumCost'] * $count;
            }
        }
        return ['metal' => $metal, 'crystal' => $crystal, 'deuterium' => $deuterium];
    }

    private static function calculateDebris(array $attackerLost, array $defenderLost, float $shipsPercent, float $defensePercent): array
    {
        $metal = 0; $crystal = 0; $deuterium = 0;
        $all = [];
        foreach ($attackerLost as $name => $count) {
            $all[$name] = ($all[$name] ?? 0) + $count;
        }
        foreach ($defenderLost as $name => $count) {
            $all[$name] = ($all[$name] ?? 0) + $count;
        }

        foreach ($all as $name => $count) {
            $stats = GameData::unit((string) $name);
            if (!$stats) {
                continue;
            }
            $pct = $stats['unitType'] === 'ship' ? $shipsPercent : $defensePercent;
            if ($pct <= 0) {
                continue;
            }
            $metal += (int) floor($stats['metalCost'] * $count * ($pct / 100));
            $crystal += (int) floor($stats['crystalCost'] * $count * ($pct / 100));
            $deuterium += (int) floor($stats['deuteriumCost'] * $count * ($pct / 100));
        }

        return ['metal' => $metal, 'crystal' => $crystal, 'deuterium' => $deuterium];
    }

    private static function calculateWreckField(array $defenderLost, array $defenderStart): array
    {
        $wreckPct = 0.7;
        $ships = [];
        $totalLostValue = 0;
        $totalStartValue = 0;

        foreach ($defenderLost as $name => $count) {
            $stats = GameData::unit((string) $name);
            if (!$stats || $stats['unitType'] !== 'ship') {
                continue;
            }
            if ($name === 'espionage_probe' || $name === 'solar_satellite') {
                continue;
            }
            $wreckCount = (int) floor($count * $wreckPct);
            if ($wreckCount > 0) {
                $ships[] = ['machineName' => $stats['machineName'], 'quantity' => $wreckCount];
            }
            $totalLostValue += ($stats['metalCost'] + $stats['crystalCost'] + $stats['deuteriumCost']) * $count;
        }

        foreach ($defenderStart as $name => $count) {
            $stats = GameData::unit((string) $name);
            if (!$stats || $stats['unitType'] !== 'ship') {
                continue;
            }
            $totalStartValue += ($stats['metalCost'] + $stats['crystalCost'] + $stats['deuteriumCost']) * $count;
        }

        if ($totalStartValue > 0) {
            $destroyedPct = ($totalLostValue / $totalStartValue) * 100;
            if ($totalLostValue >= 100_000 && $destroyedPct >= 50) {
                return [
                    'formed' => true,
                    'ships' => $ships,
                    'totalValue' => (int) floor($totalLostValue * $wreckPct),
                ];
            }
        }

        return ['formed' => false, 'ships' => [], 'totalValue' => 0];
    }

    private static function calculateMoonChance(array $debris, int $maxChance): int
    {
        $total = $debris['metal'] + $debris['crystal'] + $debris['deuterium'];
        return (int) min($maxChance, floor($total / 100_000));
    }

    private static function rollMoonCreation(int $chance): bool
    {
        return mt_rand(0, 100000) / 1000 < $chance;
    }

    private static function calculateTotalCargo(array $units): int
    {
        $total = 0;
        foreach ($units as $u) {
            $total += $u['config']['capacity'] ?? 0;
        }
        return $total;
    }

    /** Port of LootService.calculateLoot + distributeLoot. */
    public static function calculateLoot(array $defenderResources, float $lootPercentage, int $totalCargo): array
    {
        $loot = [
            'metal' => max(0, (int) floor(($defenderResources['metal'] ?? 0) * ($lootPercentage / 100))),
            'crystal' => max(0, (int) floor(($defenderResources['crystal'] ?? 0) * ($lootPercentage / 100))),
            'deuterium' => max(0, (int) floor(($defenderResources['deuterium'] ?? 0) * ($lootPercentage / 100))),
        ];

        $totalLoot = $loot['metal'] + $loot['crystal'] + $loot['deuterium'];
        if ($totalCargo >= $totalLoot) {
            return $loot;
        }
        return self::distributeLoot($loot, $totalCargo);
    }

    private static function distributeLoot(array $loot, int $totalCargo): array
    {
        $totalLoot = $loot['metal'] + $loot['crystal'] + $loot['deuterium'];
        if ($totalCargo >= $totalLoot) {
            return $loot;
        }

        $names = ['metal', 'crystal', 'deuterium'];
        $distributed = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];

        // Fair base share, capped per resource and never exceeding cargo
        $remaining = $totalCargo;
        $base = (int) floor($totalCargo / count($names));
        foreach ($names as $name) {
            $distributed[$name] = min($loot[$name], $base);
            $remaining -= $distributed[$name];
        }

        // Distribute the leftover one unit at a time (round-robin) so we
        // never exceed totalCargo (unlike the original TS loop which could
        // overshoot by the per-iteration rounding).
        $i = 0;
        while ($remaining > 0) {
            $name = $names[$i % count($names)];
            if ($distributed[$name] < $loot[$name]) {
                $distributed[$name]++;
                $remaining--;
            }
            $i++;
            if ($i > $totalCargo + count($names)) {
                break; // safety: everything full
            }
        }

        return $distributed;
    }

    private static function calculateRepairedDefenses(array $defenderUnitsLost, int $repairRate): array
    {
        $repaired = [];
        foreach ($defenderUnitsLost as $name => $count) {
            $stats = GameData::unit((string) $name);
            if (!$stats || $stats['unitType'] !== 'defense') {
                continue;
            }
            $repaired[$name] = (int) floor($count * ($repairRate / 100));
        }
        return $repaired;
    }
}
