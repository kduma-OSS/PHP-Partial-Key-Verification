<?php

declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Hash\HashInterface;
use KDuma\PKV\Hash\SuperFast;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Hash\SuperFast::class)] final class SuperFastTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $h = new SuperFast;
        $this->assertInstanceOf(HashInterface::class, $h);
    }

    public function test_deterministic_and_range(): void
    {
        $h = new SuperFast;
        $inputs = [
            '',
            'a',
            'ab',
            'abc',
            'abcd',
            'abcde',
            'abcdef',
            'abcdefg',
            'abcdefgh',           // exercises all tail branches 0..3
            '123456789',
            'The quick brown fox jumps over the lazy dog',
            "\x00",
            "\x00\xFF\x01\x02\x03",
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

    public function test_matches_reference_implementation(): void
    {
        $h = new SuperFast;
        $inputs = [
            '',
            'a',
            'ab',
            'abc',
            'abcdefg',
            '123456789',
            'hello world',
            random_bytes(5),
            random_bytes(33),
        ];

        foreach ($inputs as $data) {
            $expected = self::refSuperFast($data);
            $this->assertSame($expected, $h->compute($data), 'Mismatch vs local reference');
        }
    }

    public function test_sensitivity_to_changes(): void
    {
        $h = new SuperFast;
        $this->assertNotSame($h->compute('foo'), $h->compute('bar'));
        $this->assertNotSame($h->compute('foo'), $h->compute('foo '));
        $this->assertNotSame($h->compute('foo'), $h->compute('foO'));
    }

    /**
     * Local reference implementation mirroring the class exactly.
     * Uses LE 16-bit reads like BitConverter.ToUInt16 (little-endian).
     */
    private static function refSuperFast(string $data): int
    {
        $len = \strlen($data);
        $hash = $len & 0xFFFFFFFF;

        $rem = $len & 3;
        $pairs = $len >> 2;
        $offset = 0;

        while ($pairs-- > 0) {
            $hash = ($hash + self::u16le($data, $offset)) & 0xFFFFFFFF;
            $offset += 2;

            $next = self::u16le($data, $offset);
            $offset += 2;

            $tmp = ((($next << 11) & 0xFFFFFFFF) ^ $hash) & 0xFFFFFFFF;
            $hash = ((($hash << 16) & 0xFFFFFFFF) ^ $tmp) & 0xFFFFFFFF;
            $hash = ($hash + (($hash >> 11) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        }

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

        $hash ^= (($hash << 3) & 0xFFFFFFFF);
        $hash = ($hash + (($hash >> 5) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        $hash ^= (($hash << 4) & 0xFFFFFFFF);
        $hash = ($hash + (($hash >> 17) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        $hash ^= (($hash << 25) & 0xFFFFFFFF);
        $hash = ($hash + (($hash >> 6) & 0xFFFFFFFF)) & 0xFFFFFFFF;

        return (int) \sprintf('%u', $hash);
    }

    private static function u16le(string $data, int $offset): int
    {
        $lo = \ord($data[$offset]) & 0xFF;
        $hi = \ord($data[$offset + 1]) & 0xFF;

        return ($lo | ($hi << 8)) & 0xFFFF;
    }
}
