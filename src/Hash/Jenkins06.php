<?php

declare(strict_types=1);

namespace KDuma\PKV\Hash;

final class Jenkins06 implements HashInterface
{
    private int $seed;

    public function __construct(int $seed)
    {
        // constrain to uint32
        $this->seed = $seed & 0xFFFFFFFF;
    }

    /**
     * Compute Bob Jenkins' lookup3 (2006) 32-bit hash.
     *
     * @param  string  $data  Binary string (C# byte[] equivalent).
     * @return int Unsigned 32-bit integer (0..0xFFFFFFFF).
     */
    public function compute(string $data): int
    {
        $length = \strlen($data);
        $a = $b = $c = (0xDEADBEEF + ($length & 0xFFFFFFFF) + $this->seed) & 0xFFFFFFFF;

        // process all but the last (<=12) bytes
        $offset = 0;
        while ($length > 12) {
            $a = ($a + \ord($data[$offset++])) & 0xFFFFFFFF;
            $a = ($a + ((\ord($data[$offset++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $a = ($a + ((\ord($data[$offset++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $a = ($a + ((\ord($data[$offset++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            $b = ($b + \ord($data[$offset++])) & 0xFFFFFFFF;
            $b = ($b + ((\ord($data[$offset++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $b = ($b + ((\ord($data[$offset++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $b = ($b + ((\ord($data[$offset++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            $c = ($c + \ord($data[$offset++])) & 0xFFFFFFFF;
            $c = ($c + ((\ord($data[$offset++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $c = ($c + ((\ord($data[$offset++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $c = ($c + ((\ord($data[$offset++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            self::mix($a, $b, $c);
            $length -= 12;
        }

        // last block (<= 12 bytes), affect all bits of c
        switch ($length) { // fall-through by design
            case 12: $c = ($c + ((\ord($data[$offset + 11]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 11: $c = ($c + ((\ord($data[$offset + 10]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 10: $c = ($c + ((\ord($data[$offset + 9]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 9:  $c = ($c + (\ord($data[$offset + 8]) & 0xFF)) & 0xFFFFFFFF;
            case 8:  $b = ($b + ((\ord($data[$offset + 7]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 7:  $b = ($b + ((\ord($data[$offset + 6]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 6:  $b = ($b + ((\ord($data[$offset + 5]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 5:  $b = ($b + (\ord($data[$offset + 4]) & 0xFF)) & 0xFFFFFFFF;
            case 4:  $a = ($a + ((\ord($data[$offset + 3]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 3:  $a = ($a + ((\ord($data[$offset + 2]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 2:  $a = ($a + ((\ord($data[$offset + 1]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            case 1:  $a = ($a + (\ord($data[$offset + 0]) & 0xFF)) & 0xFFFFFFFF;
            case 0:  break; // return c; (but we still run final mix below unless length==0 in C#? No—C# returns c if 0)
        }

        // Note: In your C# code, when length == 0 at the tail switch, it returns c immediately. We mirror that behavior:
        // if the tail length is 0, we skip finalMix and return c as-is.
        if ($length === 0) {
            // C# returns c immediately for case 0
            return (int) \sprintf('%u', $c & 0xFFFFFFFF);
        }

        self::finalMix($a, $b, $c);

        return (int) \sprintf('%u', $c & 0xFFFFFFFF);
    }

    /** Rotate left on 32 bits */
    private static function rot(int $x, int $k): int
    {
        $x &= 0xFFFFFFFF;
        $left = ($x << $k) & 0xFFFFFFFF;
        $right = ($x & 0xFFFFFFFF) >> (32 - $k);

        return ($left | $right) & 0xFFFFFFFF;
    }

    /** Final scramble (matches C# Final) */
    private static function finalMix(int &$a, int &$b, int &$c): void
    {
        $c ^= $b;
        $c = ($c - self::rot($b, 14)) & 0xFFFFFFFF;
        $a ^= $c;
        $a = ($a - self::rot($c, 11)) & 0xFFFFFFFF;
        $b ^= $a;
        $b = ($b - self::rot($a, 25)) & 0xFFFFFFFF;
        $c ^= $b;
        $c = ($c - self::rot($b, 16)) & 0xFFFFFFFF;
        $a ^= $c;
        $a = ($a - self::rot($c, 4)) & 0xFFFFFFFF;
        $b ^= $a;
        $b = ($b - self::rot($a, 14)) & 0xFFFFFFFF;
        $c ^= $b;
        $c = ($c - self::rot($b, 24)) & 0xFFFFFFFF;
    }

    /** Main mixing step (matches C# Mix) */
    private static function mix(int &$a, int &$b, int &$c): void
    {
        $a = ($a - $c) & 0xFFFFFFFF;
        $a ^= self::rot($c, 4);
        $c = ($c + $b) & 0xFFFFFFFF;
        $b = ($b - $a) & 0xFFFFFFFF;
        $b ^= self::rot($a, 6);
        $a = ($a + $c) & 0xFFFFFFFF;
        $c = ($c - $b) & 0xFFFFFFFF;
        $c ^= self::rot($b, 8);
        $b = ($b + $a) & 0xFFFFFFFF;
        $a = ($a - $c) & 0xFFFFFFFF;
        $a ^= self::rot($c, 16);
        $c = ($c + $b) & 0xFFFFFFFF;
        $b = ($b - $a) & 0xFFFFFFFF;
        $b ^= self::rot($a, 19);
        $a = ($a + $c) & 0xFFFFFFFF;
        $c = ($c - $b) & 0xFFFFFFFF;
        $c ^= self::rot($b, 4);
        $b = ($b + $a) & 0xFFFFFFFF;
    }
}
