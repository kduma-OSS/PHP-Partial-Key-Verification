<?php

declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\Hash\HashInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Hash\Fnv1a::class)] final class Fnv1aTest extends TestCase
{
    /**
     * Correct reference: use a snapshot x of h for all shifts, then add once.
     */
    private static function referenceShiftAdd(string $data): int
    {
        $h = 0x811C9DC5; // offset basis
        $len = \strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $h ^= \ord($data[$i]);

            $x = $h; // snapshot!
            $sum = ($x
                    + (($x << 1) & 0xFFFFFFFF)
                    + (($x << 4) & 0xFFFFFFFF)
                    + (($x << 7) & 0xFFFFFFFF)
                    + (($x << 8) & 0xFFFFFFFF)
                    + (($x << 24) & 0xFFFFFFFF)
            ) & 0xFFFFFFFF;

            $h = $sum;
        }

        return (int) sprintf('%u', $h);
    }

    public function test_implements_interface(): void
    {
        $hash = new Fnv1a;
        $this->assertInstanceOf(HashInterface::class, $hash);
    }

    public function test_empty_returns_offset_basis(): void
    {
        $hash = new Fnv1a;
        $this->assertSame(0x811C9DC5, $hash->compute(''));
    }

    public function test_known_vector_a(): void
    {
        $hash = new Fnv1a;
        // canonical FNV-1a 32-bit for "a"
        $this->assertSame(0xE40C292C, $hash->compute('a'));
    }

    public function test_deterministic_and_range(): void
    {
        $hash = new Fnv1a;
        $inputs = ['', 'a', 'foo', 'foobar', "\x00", "\x00\xFF\x01\x02", str_repeat('xyz', 1000), random_bytes(256)];

        foreach ($inputs as $data) {
            $a = $hash->compute($data);
            $b = $hash->compute($data);

            $this->assertSame($a, $b, 'Must be deterministic');
            $this->assertIsInt($a);
            $this->assertGreaterThanOrEqual(0, $a);
            $this->assertLessThanOrEqual(0xFFFFFFFF, $a);
        }
    }

    public function test_matches_shift_add_reference(): void
    {
        $hash = new Fnv1a;

        $inputs = [
            '', 'a', 'abc', 'message digest',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
            '1234567890',
            'The quick brown fox jumps over the lazy dog',
            "\x00\x00\x00\x00",
            "\xFF\xFF\xFF\xFF",
            random_bytes(17),
            random_bytes(1024),
        ];

        foreach ($inputs as $data) {
            $expected = self::referenceShiftAdd($data);
            $actual = $hash->compute($data);
            $this->assertSame($expected, $actual, 'Mismatch vs corrected shift+add reference');
        }
    }

    public function test_different_inputs_usually_differ(): void
    {
        $hash = new Fnv1a;
        $this->assertNotSame($hash->compute('foo'), $hash->compute('bar'));
        $this->assertNotSame($hash->compute('foo'), $hash->compute('foo '));
    }
}
