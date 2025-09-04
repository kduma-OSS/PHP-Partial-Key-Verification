<?php

declare(strict_types=1);

namespace Tests\PKV\Checksum;

use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Checksum\Checksum16Interface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Checksum\Adler16::class)] final class Adler16Test extends TestCase
{
    /**
     * Reference Adler-16 implementation (mod 251, chunk 5550),
     * mirroring the original C# algorithm exactly.
     */
    private static function refAdler16(string $data): int
    {
        $a = 1;
        $b = 0;
        $len = \strlen($data);
        $offset = 0;

        while ($len > 0) {
            $tlen = $len < 5550 ? $len : 5550;
            $len -= $tlen;

            do {
                $a += \ord($data[$offset++]);
                $b += $a;
            } while (--$tlen > 0);

            $a %= 251;
            $b %= 251;
        }

        return (($b << 8) | $a) & 0xFFFF;
    }

    public function test_implements_interface(): void
    {
        $algo = new Adler16;
        $this->assertInstanceOf(Checksum16Interface::class, $algo);
    }

    public function test_empty_returns_one(): void
    {
        $algo = new Adler16;
        $this->assertSame(0x0001, $algo->compute(''));
    }

    public function test_deterministic_and_range(): void
    {
        $algo = new Adler16;

        $inputs = [
            '',
            'a',
            'abc',
            'foobar',
            "\x00",
            "\x00\xFF\x01\x02",
            str_repeat('xyz', 123),
            random_bytes(32),
        ];

        foreach ($inputs as $data) {
            $x = $algo->compute($data);
            $y = $algo->compute($data);

            $this->assertSame($x, $y, 'Checksum must be deterministic');
            $this->assertIsInt($x);
            $this->assertGreaterThanOrEqual(0, $x);
            $this->assertLessThanOrEqual(0xFFFF, $x);
        }
    }

    public function test_matches_reference_implementation(): void
    {
        $algo = new Adler16;

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

        foreach ($inputs as $data) {
            $expected = self::refAdler16($data);
            $actual = $algo->compute($data);
            $this->assertSame($expected, $actual, 'Mismatch vs reference Adler-16');
        }
    }

    public function test_chunk_boundary_behavior(): void
    {
        $algo = new Adler16;

        // Exactly one chunk
        $oneChunk = str_repeat('A', 5550);
        // Cross the boundary by 1, and by a small tail
        $boundaryPlus1 = str_repeat('A', 5551);
        $boundaryPlus100 = str_repeat('A', 5650);

        $this->assertSame(self::refAdler16($oneChunk), $algo->compute($oneChunk));
        $this->assertSame(self::refAdler16($boundaryPlus1), $algo->compute($boundaryPlus1));
        $this->assertSame(self::refAdler16($boundaryPlus100), $algo->compute($boundaryPlus100));
    }

    public function test_different_inputs_usually_differ(): void
    {
        $algo = new Adler16;

        $this->assertNotSame($algo->compute('foo'), $algo->compute('bar'));
        $this->assertNotSame($algo->compute('foo'), $algo->compute('foo '));
    }
}
