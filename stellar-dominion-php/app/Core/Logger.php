<?php

declare(strict_types=1);

namespace StellarDominion\Core;

final class Logger
{
    private const LEVELS = ['DEBUG' => 0, 'INFO' => 1, 'WARN' => 2, 'ERROR' => 3];
    private static string $category = 'SERVER';

    public static function category(string $category): void
    {
        self::$category = $category;
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);
        $level = isset(self::LEVELS[$level]) ? $level : 'INFO';
        $ts = gmdate('Y-m-d H:i:s');
        $line = sprintf("[%s] [%s] [%s] %s", $ts, self::$category, $level, $message);
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        $stream = self::LEVELS[$level] >= self::LEVELS['ERROR'] ? STDERR : STDOUT;
        fwrite($stream, $line . PHP_EOL);
    }

    public static function info(string $message, array $context = []): void { self::log('INFO', $message, $context); }
    public static function warn(string $message, array $context = []): void { self::log('WARN', $message, $context); }
    public static function error(string $message, array $context = []): void { self::log('ERROR', $message, $context); }
    public static function debug(string $message, array $context = []): void { self::log('DEBUG', $message, $context); }
}
