<?php

declare(strict_types=1);

namespace StellarDominion\Core;

/**
 * Loads and caches configuration arrays from app/Config/*.php
 */
final class Config
{
    private static ?array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!isset(self::$cache[$file])) {
            $path = dirname(__DIR__) . '/Config/' . $file . '.php';
            self::$cache[$file] = is_file($path) ? require $path : [];
        }

        $value = self::$cache[$file];
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        self::$cache[$file] ??= [];
        $ref = &self::$cache[$file];
        foreach ($segments as $segment) {
            $ref[$segment] ??= [];
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }
}
