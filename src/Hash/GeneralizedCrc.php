<?php

declare(strict_types=1);

namespace KDuma\PKV\Hash;

final class GeneralizedCrc implements HashInterface
{
    /** @var array<int,int>|null */
    private static ?array $table = null;

    public function compute(string $data): int
    {
        $len = \strlen($data);
        $hash = (int) $len & 0xFFFFFFFF;

        for ($i = 0; $i < $len; $i++) {
            $idx = (($hash & 0xFF) ^ \ord($data[$i])) & 0xFF;
            $hash = ((($hash >> 8) & 0xFFFFFFFF) ^ self::table()[$idx]) & 0xFFFFFFFF;
        }

        // ensure unsigned 32-bit range (0..0xFFFFFFFF)
        return (int) \sprintf('%u', $hash);
    }

    /** @return array<int,int> */
    private static function table(): array
    {
        if (self::$table !== null) {
            return self::$table;
        }

        $table = [];

        // Fill table with "random permutations" per Bob Jenkins’ gencrc.c
        for ($i = 0; $i < 256; $i++) {
            $x = $i & 0xFF;

            // 1st phase (5 rounds; +1)
            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 1) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val = $x & 0xFF;

            // 2nd phase (5 rounds; +2), mix into bits 8..15
            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 2) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val ^= (($x & 0xFF) << 8);

            // 3rd phase (5 rounds; +3), mix into bits 16..23
            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 3) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val ^= (($x & 0xFF) << 16);

            // 4th phase (5 rounds; +4), mix into bits 24..31
            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 4) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val ^= (($x & 0xFF) << 24);

            $table[$i] = $val & 0xFFFFFFFF;
        }

        self::$table = $table;

        return self::$table;
    }
}
