<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

/**
 * Central registry for all game data (configs + unit database).
 *
 * The unit database mirrors server/combat/BattleEngine.ts OGameShipDatabase
 * plus the newer ship classes (interceptor, carrier, dreadnought, titan,
 * flagship, explorer, terraform_ship, science_vessel, repair_ship,
 * mining_ship, salvage_ship, construction_ship, hospital_ship,
 * missile_battery, orbital_defense_platform, planetary_cannon,
 * defense_satellites).
 */
final class GameData
{
    private static ?array $ships = null;
    private static ?array $defenses = null;

    public static function ships(): array
    {
        if (self::$ships === null) {
            self::$ships = require dirname(__DIR__) . '/Config/ships.php';
        }
        return self::$ships;
    }

    public static function defenses(): array
    {
        if (self::$defenses === null) {
            self::$defenses = require dirname(__DIR__) . '/Config/defenses.php';
        }
        return self::$defenses;
    }

    /**
     * Unified unit database keyed by machineName (snake_case).
     * Mirrors OGameShipDatabase. Each entry carries unitType ship|defense.
     *
     * @return array<string, array{metalCost:int,crystalCost:int,deuteriumCost:int,unitType:string,machineName:string,rapidfire:array,int}>
     */
    public static function unitDatabase(): array
    {
        static $db = null;
        if ($db !== null) {
            return $db;
        }

        $db = [];

        $shipConfig = self::ships();
        $rapidFire = $shipConfig['RAPID_FIRE'] ?? [];
        $shipParams = $shipConfig['UNIT_PARAMS'] ?? [];

        foreach ($shipParams as $key => $ship) {
            $db[$ship['id']] = [
                'id' => $ship['id'],
                'machineName' => $ship['id'],
                'name' => $ship['name'],
                'structuralIntegrity' => $ship['structuralIntegrity'],
                'shield' => $ship['shield'],
                'attack' => $ship['attack'],
                'speed' => $ship['speed'],
                'capacity' => $ship['capacity'],
                'fuelConsumption' => $ship['fuelConsumption'],
                'unitType' => 'ship',
                'metalCost' => $ship['cost']['metal'],
                'crystalCost' => $ship['cost']['crystal'],
                'deuteriumCost' => $ship['cost']['deuterium'],
                'rapidfire' => $rapidFire[$ship['id']] ?? [],
            ];
        }

        $defenseConfig = self::defenses();
        foreach (($defenseConfig['defenses'] ?? []) as $key => $def) {
            $db[$def['id']] = [
                'id' => $def['id'],
                'machineName' => $def['id'],
                'name' => $def['name'],
                'structuralIntegrity' => $def['structuralIntegrity'],
                'shield' => $def['shield'],
                'attack' => $def['attack'],
                'speed' => 0,
                'capacity' => 0,
                'fuelConsumption' => 0,
                'unitType' => 'defense',
                'metalCost' => $def['cost']['metal'],
                'crystalCost' => $def['cost']['crystal'],
                'deuteriumCost' => $def['cost']['deuterium'],
                'rapidfire' => [],
            ];
        }

        return $db;
    }

    /** Look up one unit; returns null when unknown. */
    public static function unit(?string $machineName): ?array
    {
        if ($machineName === null) {
            return null;
        }
        return self::unitDatabase()[$machineName] ?? null;
    }

    public static function buildings(): array
    {
        static $buildings = null;
        if ($buildings === null) {
            $config = require dirname(__DIR__) . '/Config/buildings.php';
            $buildings = $config['buildings'];
        }
        return $buildings;
    }

    public static function building(string $key): ?array
    {
        return self::buildings()[$key] ?? null;
    }

    public static function technologies(): array
    {
        static $techs = null;
        if ($techs === null) {
            $config = require dirname(__DIR__) . '/Config/technologies.php';
            $techs = $config['technologies'];
        }
        return $techs;
    }

    public static function technology(string $key): ?array
    {
        return self::technologies()[$key] ?? null;
    }

    public static function realms(): array
    {
        static $realms = null;
        if ($realms === null) {
            $config = require dirname(__DIR__) . '/Config/realms.php';
            $realms = $config['realms'];
        }
        return $realms;
    }

    public static function enemyRaces(): array
    {
        static $races = null;
        if ($races === null) {
            $config = require dirname(__DIR__) . '/Config/enemyRaces.php';
            $races = $config['enemy_races'];
        }
        return $races;
    }
}
