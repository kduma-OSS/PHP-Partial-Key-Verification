<?php

declare(strict_types=1);

namespace KDuma\PKV\Hash;

use KDuma\PKV\Checksum\Checksum32Interface;

/**
 * Computes a CRC-32 checksum/hash.
 * Poly: 0x04C11DB7, Init: 0xFFFFFFFF, Refin: true, Refout: true, Xorout: 0xFFFFFFFF.
 * Matches the standard CRC-32/ISO-HDLC.
 */
final class Crc32 implements Checksum32Interface, HashInterface
{
    /** @var array<int,int>|null */
    private static ?array $table = null;

    public function compute(string $data): int
    {
        $remainder = 0xFFFFFFFF;

        $len = \strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $byte = \ord($data[$i]);
            $index = ($this->reflect($byte, 8) ^ (($remainder >> 24) & 0xFF)) & 0xFF;
            $remainder = (self::table()[$index] ^ (($remainder << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }

        $final = ($this->reflect($remainder, 32) ^ 0xFFFFFFFF) & 0xFFFFFFFF;

        // Ensure unsigned 32-bit
        return (int) sprintf('%u', $final);
    }

    /** @return array<int,int> */
    private static function table(): array
    {
        if (self::$table !== null) {
            return self::$table;
        }

        $table = [];
        $topbit = 1 << 31;

        for ($dividend = 0; $dividend < 256; $dividend++) {
            $remainder = ($dividend << 24) & 0xFFFFFFFF;

            for ($bit = 8; $bit > 0; $bit--) {
                if (($remainder & $topbit) !== 0) {
                    $remainder = ((($remainder << 1) & 0xFFFFFFFF) ^ 0x04C11DB7) & 0xFFFFFFFF;
                } else {
                    $remainder = ($remainder << 1) & 0xFFFFFFFF;
                }
            }

            $table[$dividend] = $remainder & 0xFFFFFFFF;
        }

        self::$table = $table;

        return self::$table;
    }

    private function reflect(int $data, int $nBits): int
    {
        $reflection = 0;
        for ($bit = 0; $bit < $nBits; $bit++) {
            if (($data & 0x01) !== 0) {
                $reflection |= (1 << (($nBits - 1) - $bit));
            }
            $data >>= 1;
        }

        return $reflection & ((1 << $nBits) - 1);
    }
}
