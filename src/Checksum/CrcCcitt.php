<?php

declare(strict_types=1);

namespace KDuma\PKV\Checksum;

/**
 * CRC-CCITT (False): poly 0x1021, init 0xFFFF, refin=false, refout=false, xorout=0x0000.
 * Matches the original C# implementation.
 */
final class CrcCcitt implements Checksum16Interface
{
    /** @var array<int,int>|null */
    private static ?array $table = null;

    public function compute(string $data): int
    {
        $remainder = 0xFFFF; // ushort

        // Divide the message by the polynomial, a byte at a time.
        $len = \strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $byte = \ord($data[$i]) & 0xFF;
            $index = ($byte ^ (($remainder >> 8) & 0xFF)) & 0xFF;
            $remainder = (self::table()[$index] ^ (($remainder << 8) & 0xFFFF)) & 0xFFFF;
        }

        return $remainder & 0xFFFF;
    }

    /** @return array<int,int> */
    private static function table(): array
    {
        if (self::$table !== null) {
            return self::$table;
        }

        $table = [];
        $topbit = 1 << 15;

        // Compute the remainder of each possible dividend.
        for ($dividend = 0; $dividend < 256; $dividend++) {
            // Start with the dividend followed by zeros.
            $remainder = ($dividend << 8) & 0xFFFF;

            // Perform modulo-2 division, a bit at a time.
            for ($bit = 8; $bit > 0; $bit--) {
                if (($remainder & $topbit) !== 0) {
                    $remainder = ((($remainder << 1) & 0xFFFF) ^ 0x1021) & 0xFFFF;
                } else {
                    $remainder = (($remainder << 1) & 0xFFFF);
                }
            }

            $table[$dividend] = $remainder & 0xFFFF;
        }

        self::$table = $table;

        return self::$table;
    }
}
