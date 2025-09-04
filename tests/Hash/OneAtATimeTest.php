<?php

declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Hash\HashInterface;
use KDuma\PKV\Hash\OneAtATime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Hash\OneAtATime::class)] final class OneAtATimeTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $h = new OneAtATime;
        $this->assertInstanceOf(HashInterface::class, $h);
    }

    public function test_deterministic_and_range(): void
    {
        $h = new OneAtATime;
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
            $a = $h->compute($data);
            $b = $h->compute($data);

            $this->assertSame($a, $b, 'Must be deterministic');
            $this->assertIsInt($a);
            $this->assertGreaterThanOrEqual(0, $a);
            $this->assertLessThanOrEqual(0xFFFFFFFF, $a);
        }
    }

    public function test_matches_local_reference(): void
    {
        $h = new OneAtATime;

        $inputs = [
            '',
            'a',
            'abc',
            'foobar',
            '123456789',
            'hello world',
            random_bytes(5),
            random_bytes(33),
        ];

        foreach ($inputs as $data) {
            $expected = self::refOaat($data);
            $this->assertSame($expected, $h->compute($data), 'Mismatch vs local reference');
        }
    }

    public function test_sensitivity_to_changes(): void
    {
        $h = new OneAtATime;
        $this->assertNotSame($h->compute('foo'), $h->compute('bar'));
        $this->assertNotSame($h->compute('foo'), $h->compute('foo '));
        $this->assertNotSame($h->compute('foo'), $h->compute('foO'));
    }

    /**
     * Local reference implementation mirroring the class exactly.
     */
    private static function refOaat(string $data): int
    {
        $hash = 0;
        $len = \strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash + \ord($data[$i])) & 0xFFFFFFFF;
            $hash = ($hash + (($hash << 10) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $hash ^= (($hash >> 6) & 0xFFFFFFFF);
        }

        $hash = ($hash + (($hash << 3) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        $hash ^= (($hash >> 11) & 0xFFFFFFFF);
        $hash = ($hash + (($hash << 15) & 0xFFFFFFFF)) & 0xFFFFFFFF;

        return (int) \sprintf('%u', $hash);
    }
}
