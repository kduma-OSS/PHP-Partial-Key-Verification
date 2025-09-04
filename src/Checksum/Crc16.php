<?php

declare(strict_types=1);

namespace KDuma\PKV\Checksum;

use function ord;
use function strlen;

final class Crc16 implements Checksum16Interface
{
    /** @var array<int,int>|null */
    private static ?array $table = null;

    public function compute(string $data): int
    {
        $remainder = 0; // ushort

        // Divide the message by the polynomial, a byte at a time.
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($data[$i]);
            $index = (($this->reflect((int) $byte, 8) ^ (($remainder >> 8) & 0xFF)) & 0xFF);
            $remainder = (self::table()[$index] ^ (($remainder << 8) & 0xFFFF)) & 0xFFFF;
        }

        return $this->reflect($remainder, 16) & 0xFFFF;
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
                    $remainder = ((($remainder << 1) & 0xFFFF) ^ 0x8005) & 0xFFFF;
                } else {
                    $remainder = (($remainder << 1) & 0xFFFF);
                }
            }

            $table[$dividend] = $remainder & 0xFFFF;
        }

        self::$table = $table;

        return self::$table;
    }

    /**
     * Reflect the lower nBits of $data about the center bit.
     *
     * @param  int  $data  Unsigned integer
     * @param  int  $nBits  Number of bits to reflect (8 or 16)
     */
    private function reflect(int $data, int $nBits): int
    {
        $reflection = 0;

        for ($bit = 0; $bit < $nBits; $bit++) {
            if (($data & 0x01) !== 0) {
                $reflection |= (1 << (($nBits - 1) - $bit));
            }
            $data >>= 1;
        }

        // mask down to nBits
        return $reflection & ((1 << $nBits) - 1);
    }
}
