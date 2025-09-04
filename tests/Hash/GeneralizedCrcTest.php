<?php

declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Hash\GeneralizedCrc;
use KDuma\PKV\Hash\HashInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Hash\GeneralizedCrc::class)] final class GeneralizedCrcTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $g = new GeneralizedCrc;
        $this->assertInstanceOf(HashInterface::class, $g);
    }

    public function test_deterministic_and_range(): void
    {
        $g = new GeneralizedCrc;
        $inputs = [
            '',
            'a',
            'abc',
            'foobar',
            "\x00\xFF\x01\x02",
            str_repeat('xyz', 100),
            random_bytes(64),
        ];

        foreach ($inputs as $data) {
            $a = $g->compute($data);
            $b = $g->compute($data);

            $this->assertSame($a, $b, 'Must be deterministic');
            $this->assertIsInt($a);
            $this->assertGreaterThanOrEqual(0, $a);
            $this->assertLessThanOrEqual(0xFFFFFFFF, $a);
        }
    }

    public function test_matches_reference_implementation(): void
    {
        $g = new GeneralizedCrc;

        $inputs = [
            '',
            'a',
            'abc',
            '123456789',
            'hello world',
            random_bytes(17),
            random_bytes(1024),
        ];

        foreach ($inputs as $data) {
            $expected = self::referenceGeneralizedCrc($data);
            $this->assertSame($expected, $g->compute($data), 'Mismatch vs reference implementation');
        }
    }

    public function test_pinned_vectors(): void
    {
        $g = new GeneralizedCrc;

        // Pinned values from the reference implementation
        $this->assertSame(0x00000000, $g->compute(''));
        $this->assertSame(0x9DC3B961, $g->compute('a'));
        $this->assertSame(0x66F78E90, $g->compute('abc'));
        $this->assertSame(0x55CE33CE, $g->compute('123456789'));
        $this->assertSame(0x22078CAF, $g->compute('hello world'));
    }

    public function test_different_inputs_usually_differ(): void
    {
        $g = new GeneralizedCrc;
        $this->assertNotSame($g->compute('foo'), $g->compute('bar'));
        $this->assertNotSame($g->compute('foo'), $g->compute('foo '));
        $this->assertNotSame($g->compute('foo'), $g->compute('foO'));
    }

    /**
     * Local reference implementation (mirrors the PHP class exactly)
     * so we don't depend on external libs/vectors.
     */
    private static function referenceGeneralizedCrc(string $data): int
    {
        $table = self::buildTable();
        $len = \strlen($data);
        $hash = $len & 0xFFFFFFFF;

        for ($i = 0; $i < $len; $i++) {
            $idx = (($hash & 0xFF) ^ \ord($data[$i])) & 0xFF;
            $hash = ((($hash >> 8) & 0xFFFFFFFF) ^ $table[$idx]) & 0xFFFFFFFF;
        }

        return (int) \sprintf('%u', $hash);
    }

    /** @return array<int,int> */
    private static function buildTable(): array
    {
        $table = [];

        for ($i = 0; $i < 256; $i++) {
            $x = $i & 0xFF;

            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 1) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val = $x & 0xFF;

            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 2) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val ^= (($x & 0xFF) << 8);

            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 3) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val ^= (($x & 0xFF) << 16);

            for ($j = 0; $j < 5; $j++) {
                $x = ($x + 4) & 0xFF;
                $x = ($x + (($x << 1) & 0xFF)) & 0xFF;
                $x ^= ($x >> 1);
                $x &= 0xFF;
            }
            $val ^= (($x & 0xFF) << 24);

            $table[$i] = $val & 0xFFFFFFFF;
        }

        return $table;
    }
}
