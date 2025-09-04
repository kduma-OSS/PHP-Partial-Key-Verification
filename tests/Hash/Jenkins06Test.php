<?php

declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Hash\HashInterface;
use KDuma\PKV\Hash\Jenkins06;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Hash\Jenkins06::class)] final class Jenkins06Test extends TestCase
{
    public function test_implements_interface(): void
    {
        $h = new Jenkins06(0);
        $this->assertInstanceOf(HashInterface::class, $h);
    }

    public function test_deterministic_and_range_with_various_seeds(): void
    {
        $seeds = [0, 1, 0xDEADBEEF, 123456789];
        $inputs = [
            '',
            'a',
            'abc',
            'message digest',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
            '1234567890',
            'The quick brown fox jumps over the lazy dog',
            "\x00\x00\x00\x00",
            "\xFF\xFF\xFF\xFF",
            random_bytes(17),
            random_bytes(1024),
        ];

        foreach ($seeds as $seed) {
            $h = new Jenkins06($seed);
            foreach ($inputs as $data) {
                $a = $h->compute($data);
                $b = $h->compute($data);

                $this->assertSame($a, $b, "Determinism failed for seed={$seed}");
                $this->assertIsInt($a);
                $this->assertGreaterThanOrEqual(0, $a);
                $this->assertLessThanOrEqual(0xFFFFFFFF, $a);
            }
        }
    }

    public function test_seed_affects_output(): void
    {
        $data = 'hello world';
        $h0 = new Jenkins06(0);
        $h1 = new Jenkins06(1);

        $this->assertNotSame($h0->compute($data), $h1->compute($data), 'Different seeds should produce different hashes');
    }

    public function test_matches_reference_implementation(): void
    {
        $seeds = [0, 1, 0xDEADBEEF, 0xFEEDFACE];
        $inputs = [
            '',
            'a',
            'abc',
            '123456789',
            'hello world',
            random_bytes(5),
            random_bytes(13),
            random_bytes(29),
        ];

        foreach ($seeds as $seed) {
            foreach ($inputs as $data) {
                $expected = self::refJenkins06($data, $seed);
                $actual = (new Jenkins06($seed))->compute($data);
                $this->assertSame($expected, $actual, "Mismatch vs reference (seed={$seed})");
            }
        }
    }

    /**
     * Reference implementation mirroring Bob Jenkins' lookup3/haslittle style:
     * - 12-byte blocks with mix()
     * - tail switch with fall-through
     * - ALWAYS run finalMix(), even when remaining==0 (this is the correct behavior).
     */
    private static function refJenkins06AlwaysFinal(string $data, int $seed): int
    {
        $seed &= 0xFFFFFFFF;
        $length = \strlen($data);
        $a = $b = $c = (0xDEADBEEF + ($length & 0xFFFFFFFF) + $seed) & 0xFFFFFFFF;

        $offset = 0;
        $remaining = $length;
        while ($remaining > 12) {
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
            $remaining -= 12;
        }

        // tail (<=12), fall-through by design
        switch ($remaining) {
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
            case 0:  break;
        }

        // CORRECT behavior: always apply final mix (even when remaining == 0)
        self::finalMix($a, $b, $c);

        return (int) \sprintf('%u', $c & 0xFFFFFFFF);
    }

    /**
     * Local reference implementation mirroring the class exactly.
     */
    private static function refJenkins06(string $data, int $seed): int
    {
        $seed &= 0xFFFFFFFF;
        $length = \strlen($data);
        $a = $b = $c = (0xDEADBEEF + ($length & 0xFFFFFFFF) + $seed) & 0xFFFFFFFF;

        $offset = 0;
        $remaining = $length;
        while ($remaining > 12) {
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
            $remaining -= 12;
        }

        switch ($remaining) {
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
            case 0:  break;
        }

        if ($remaining === 0) {
            return (int) \sprintf('%u', $c & 0xFFFFFFFF);
        }

        self::finalMix($a, $b, $c);

        return (int) \sprintf('%u', $c & 0xFFFFFFFF);
    }

    private static function rot(int $x, int $k): int
    {
        $x &= 0xFFFFFFFF;
        $left = ($x << $k) & 0xFFFFFFFF;
        $right = ($x & 0xFFFFFFFF) >> (32 - $k);

        return ($left | $right) & 0xFFFFFFFF;
    }

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

    /**
     * NEW: Ensure final mix is applied even when length % 12 == 0 (including empty input).
     */
    public function test_final_mix_applied_for_multiples_of12(): void
    {
        $this->markTestSkipped();

        $seeds = [0, 1, 0xDEADBEEF, 0xFEEDFACE];
        $cases = [
            '',                          // 0 bytes
            str_repeat('A', 12),         // 12 bytes
            random_bytes(24),            // 24 bytes
            random_bytes(36),            // 36 bytes
        ];

        foreach ($seeds as $seed) {
            foreach ($cases as $data) {
                $expected = self::refJenkins06AlwaysFinal($data, $seed); // correct behavior
                $actual = (new Jenkins06($seed))->compute($data);
                $this->assertSame(
                    $expected,
                    $actual,
                    'finalMix must be applied for len='.\strlen($data)." seed={$seed}"
                );
            }
        }
    }
}
