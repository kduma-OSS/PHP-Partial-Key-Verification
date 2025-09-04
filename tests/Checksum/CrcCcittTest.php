<?php

declare(strict_types=1);

namespace Tests\PKV\Checksum;

use KDuma\PKV\Checksum\Checksum16Interface;
use KDuma\PKV\Checksum\CrcCcitt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Checksum\CrcCcitt::class)] final class CrcCcittTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $crc = new CrcCcitt;
        $this->assertInstanceOf(Checksum16Interface::class, $crc);
    }

    public function test_empty_is_ffff(): void
    {
        $crc = new CrcCcitt;
        $this->assertSame(0xFFFF, $crc->compute(''));
    }

    public function test_known_vector_123456789(): void
    {
        // CRC-CCITT (False) check value:
        // "123456789" -> 0x29B1
        $crc = new CrcCcitt;
        $this->assertSame(0x29B1, $crc->compute('123456789'));
    }

    public function test_deterministic_and_range(): void
    {
        $crc = new CrcCcitt;

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
        $crc = new CrcCcitt;

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
            $expected = self::refCrcCcittFalse($data);
            $actual = $crc->compute($data);
            $this->assertSame($expected, $actual, 'Mismatch vs reference CRC-CCITT (False)');
        }
    }

    public function test_sensitivity_to_changes(): void
    {
        $crc = new CrcCcitt;

        $this->assertNotSame($crc->compute('foo'), $crc->compute('bar'));
        $this->assertNotSame($crc->compute('foo'), $crc->compute('foo '));
        $this->assertNotSame($crc->compute('foo'), $crc->compute('foO'));
    }

    /**
     * Reference implementation mirroring the algorithm in the class:
     * poly 0x1021, init 0xFFFF, refin=false, refout=false, xorout=0x0000.
     */
    private static function refCrcCcittFalse(string $data): int
    {
        $remainder = 0xFFFF;

        $len = \strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $byte = \ord($data[$i]) & 0xFF;
            $index = ($byte ^ (($remainder >> 8) & 0xFF)) & 0xFF;
            $remainder = (self::table()[$index] ^ (($remainder << 8) & 0xFFFF)) & 0xFFFF;
        }

        return $remainder & 0xFFFF;
    }

    /** cache for reference table */
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
                    $remainder = ((($remainder << 1) & 0xFFFF) ^ 0x1021) & 0xFFFF;
                } else {
                    $remainder = (($remainder << 1) & 0xFFFF);
                }
            }

            $table[$dividend] = $remainder & 0xFFFF;
        }

        self::$table = $table;

        return self::$table;
    }
}
