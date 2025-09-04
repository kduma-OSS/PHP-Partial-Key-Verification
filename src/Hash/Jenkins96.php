<?php

declare(strict_types=1);

namespace KDuma\PKV\Hash;

final class Jenkins96 implements HashInterface
{
    /**
     * Compute a 32-bit hash using Bob Jenkins' lookup2 (1996) algorithm.
     *
     * @param  string  $data  Binary string (equivalent to C# byte[]).
     * @return int Unsigned 32-bit integer (0..0xFFFFFFFF).
     */
    public function compute(string $data): int
    {
        $len = \strlen($data);
        // internal state (uint32)
        $a = 0x9E3779B9;
        $b = 0x9E3779B9;
        $c = 0;

        $i = 0;

        // Process 12-byte blocks
        while ($i + 12 <= $len) {
            $a = ($a + self::u32ord($data[$i++])) & 0xFFFFFFFF;
            $a = ($a + ((self::u32ord($data[$i++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $a = ($a + ((self::u32ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $a = ($a + ((self::u32ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            $b = ($b + self::u32ord($data[$i++])) & 0xFFFFFFFF;
            $b = ($b + ((self::u32ord($data[$i++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $b = ($b + ((self::u32ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $b = ($b + ((self::u32ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            $c = ($c + self::u32ord($data[$i++])) & 0xFFFFFFFF;
            $c = ($c + ((self::u32ord($data[$i++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $c = ($c + ((self::u32ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $c = ($c + ((self::u32ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            self::mix($a, $b, $c);
        }

        // Tail
        $c = ($c + ($len & 0xFFFFFFFF)) & 0xFFFFFFFF;

        if ($i < $len) {
            $a = ($a + self::u32ord($data[$i++])) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $a = ($a + ((self::u32ord($data[$i++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $a = ($a + ((self::u32ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $a = ($a + ((self::u32ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }

        if ($i < $len) {
            $b = ($b + self::u32ord($data[$i++])) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $b = ($b + ((self::u32ord($data[$i++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $b = ($b + ((self::u32ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $b = ($b + ((self::u32ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }

        if ($i < $len) {
            $c = ($c + ((self::u32ord($data[$i++]) << 8) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $c = ($c + ((self::u32ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }
        if ($i < $len) {
            $c = ($c + ((self::u32ord($data[$i]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }

        self::mix($a, $b, $c);

        return (int) \sprintf('%u', $c & 0xFFFFFFFF);
    }

    /** Return 0..255 for a byte char */
    private static function u32ord(string $ch): int
    {
        return \ord($ch) & 0xFF;
    }

    /** The lookup2 "mix" */
    private static function mix(int &$a, int &$b, int &$c): void
    {
        $a = ($a - $b - $c) & 0xFFFFFFFF;
        $a ^= ($c >> 13);
        $b = ($b - $c - $a) & 0xFFFFFFFF;
        $b ^= (($a << 8) & 0xFFFFFFFF);
        $c = ($c - $a - $b) & 0xFFFFFFFF;
        $c ^= ($b >> 13);
        $a = ($a - $b - $c) & 0xFFFFFFFF;
        $a ^= ($c >> 12);
        $b = ($b - $c - $a) & 0xFFFFFFFF;
        $b ^= (($a << 16) & 0xFFFFFFFF);
        $c = ($c - $a - $b) & 0xFFFFFFFF;
        $c ^= ($b >> 5);
        $a = ($a - $b - $c) & 0xFFFFFFFF;
        $a ^= ($c >> 3);
        $b = ($b - $c - $a) & 0xFFFFFFFF;
        $b ^= (($a << 10) & 0xFFFFFFFF);
        $c = ($c - $a - $b) & 0xFFFFFFFF;
        $c ^= ($b >> 15);
    }
}
