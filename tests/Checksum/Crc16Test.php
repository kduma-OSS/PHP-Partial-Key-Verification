<?php

declare(strict_types=1);

namespace Tests\PKV\Checksum;

use KDuma\PKV\Checksum\Checksum16Interface;
use KDuma\PKV\Checksum\Crc16;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Checksum\Crc16::class)] final class Crc16Test extends TestCase
{
    /**
     * Reference implementation that mirrors the C# logic exactly (including
     * reflect-per-byte and final reflection, poly 0x8005, init 0x0000).
     */
    private static function refCrc16(string $data): int
    {
        $remainder = 0; // ushort

        $len = \strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $byte = \ord($data[$i]);
            $index = (self::reflect($byte, 8) ^ (($remainder >> 8) & 0xFF)) & 0xFF;
            $remainder = (self::table()[$index] ^ (($remainder << 8) & 0xFFFF)) & 0xFFFF;
        }

        return self::reflect($remainder, 16) & 0xFFFF;
    }

    /** reflect helper for reference */
    private static function reflect(int $data, int $nBits): int
    {
        $reflection = 0;
        for ($bit = 0; $bit < $nBits; $bit++) {
            if (($data & 0x01) !== 0) {
                $reflection |= (1 << (($nBits - 1) - $bit));
            }
            $data >>= 1;
        }

        return $reflection & ((1 << $nBits) - 1);
    }

    /** precompute reference table */
    private static ?array $table = null;

    /** @return array<int,int> */
    private static function table(): array
    {
        if (self::$table !== null) {
            return self::$table;
        }

        $table = [];
        $topbit = 1 << 15;

        for ($dividend = 0; $dividend < 256; $dividend++) {
            $remainder = ($dividend << 8) & 0xFFFF;

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

    public function test_implements_interface(): void
    {
        $crc = new Crc16;
        $this->assertInstanceOf(Checksum16Interface::class, $crc);
    }

    public function test_empty_is_zero(): void
    {
        $crc = new Crc16;
        $this->assertSame(0x0000, $crc->compute(''));
    }

    public function test_known_vector_123456789(): void
    {
        // This algorithm matches CRC-16/IBM (ARC): poly 0x8005, init 0x0000,
        // refin/refout true, xorout 0x0000.
        // Standard check value: "123456789" -> 0xBB3D
        $crc = new Crc16;
        $this->assertSame(0xBB3D, $crc->compute('123456789'));
    }

    public function test_deterministic_and_range(): void
    {
        $crc = new Crc16;

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
            $a = $crc->compute($data);
            $b = $crc->compute($data);

            $this->assertSame($a, $b, 'CRC must be deterministic');
            $this->assertIsInt($a);
            $this->assertGreaterThanOrEqual(0, $a);
            $this->assertLessThanOrEqual(0xFFFF, $a);
        }
    }

    public function test_matches_reference_implementation(): void
    {
        $crc = new Crc16;

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
            $expected = self::refCrc16($data);
            $actual = $crc->compute($data);
            $this->assertSame($expected, $actual, 'Mismatch vs reference C#-equivalent implementation');
        }
    }

    public function test_sensitivity_to_changes(): void
    {
        $crc = new Crc16;

        $this->assertNotSame($crc->compute('foo'), $crc->compute('bar'));
        $this->assertNotSame($crc->compute('foo'), $crc->compute('foo '));
        $this->assertNotSame($crc->compute('foo'), $crc->compute('foO'));
    }
}
