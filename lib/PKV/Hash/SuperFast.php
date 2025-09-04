<?php
declare(strict_types=1);

namespace KDuma\PKV\Hash;

final class SuperFast implements HashInterface
{
    /**
     * Compute the SuperFast hash of a binary string.
     *
     * @param string $data Binary string (equivalent to C# byte[]).
     * @return int Unsigned 32-bit integer (0..0xFFFFFFFF).
     */
    public function compute(string $data): int
    {
        $len = \strlen($data);
        $hash = $len & 0xFFFFFFFF;

        $rem = $len & 3;          // bytes remaining after 16-bit pairs
        $pairs = $len >> 2;       // number of 16-bit pairs
        $offset = 0;

        // Main loop (2× uint16 per iteration, little-endian)
        while ($pairs-- > 0) {
            $hash = ($hash + self::u16le($data, $offset)) & 0xFFFFFFFF;
            $offset += 2;

            $next = self::u16le($data, $offset);
            $offset += 2;

            $tmp  = ((($next << 11) & 0xFFFFFFFF) ^ $hash) & 0xFFFFFFFF;
            $hash = ((($hash << 16) & 0xFFFFFFFF) ^ $tmp) & 0xFFFFFFFF;
            $hash = ($hash + (($hash >> 11) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }

        // Tail
        switch ($rem) {
            case 3:
                $hash = ($hash + self::u16le($data, $offset)) & 0xFFFFFFFF;
                $offset += 2;
                $hash ^= (($hash << 16) & 0xFFFFFFFF);
                $hash ^= ((\ord($data[$offset]) & 0xFF) << 18);
                $hash = ($hash + (($hash >> 11) & 0xFFFFFFFF)) & 0xFFFFFFFF;
                break;

            case 2:
                $hash = ($hash + self::u16le($data, $offset)) & 0xFFFFFFFF;
                $hash ^= (($hash << 11) & 0xFFFFFFFF);
                $hash = ($hash + (($hash >> 17) & 0xFFFFFFFF)) & 0xFFFFFFFF;
                break;

            case 1:
                $hash = ($hash + (\ord($data[$offset]) & 0xFF)) & 0xFFFFFFFF;
                $hash ^= (($hash << 10) & 0xFFFFFFFF);
                $hash = ($hash + (($hash >> 1) & 0xFFFFFFFF)) & 0xFFFFFFFF;
                break;

            case 0:
                // nothing
                break;
        }

        // Final avalanching of 127 bits
        $hash ^= (($hash << 3) & 0xFFFFFFFF);
        $hash = ($hash + (($hash >> 5) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        $hash ^= (($hash << 4) & 0xFFFFFFFF);
        $hash = ($hash + (($hash >> 17) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        $hash ^= (($hash << 25) & 0xFFFFFFFF);
        $hash = ($hash + (($hash >> 6) & 0xFFFFFFFF)) & 0xFFFFFFFF;

        return (int) \sprintf('%u', $hash);
    }

    /** Read 16-bit little-endian unsigned from $data at $offset (no bounds check). */
    private static function u16le(string $data, int $offset): int
    {
        // little-endian: low byte + (high byte << 8)
        $lo = \ord($data[$offset]) & 0xFF;
        $hi = \ord($data[$offset + 1]) & 0xFF;
        return ($lo | ($hi << 8)) & 0xFFFF;
    }
}
