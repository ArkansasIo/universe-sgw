<?php

declare(strict_types=1);

namespace StellarDominion\Core;

final class UUID
{
    /** Generate a v4 UUID string (matches the original gen_random_uuid() varchar PKs). */
    public static function v4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant 10xx

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
