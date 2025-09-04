<?php

declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Checksum\Checksum32Interface;
use KDuma\PKV\Hash\Crc32;
use KDuma\PKV\Hash\HashInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Hash\Crc32::class)] final class Crc32Test extends TestCase
{
    public function test_implements_interfaces(): void
    {
        $crc = new Crc32;
        $this->assertInstanceOf(HashInterface::class, $crc);
        $this->assertInstanceOf(Checksum32Interface::class, $crc);
    }

    public function test_empty_is_zero(): void
    {
        // Standard CRC-32: "" -> 0x00000000
        $crc = new Crc32;
        $this->assertSame(0x00000000, $crc->compute(''));
    }

    public function test_known_vector_123456789(): void
    {
        // Standard CRC-32: "123456789" -> 0xCBF43926
        $crc = new Crc32;
        $this->assertSame(0xCBF43926, $crc->compute('123456789'));
    }

    public function test_deterministic_and_range(): void
    {
        $crc = new Crc32;
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
            $this->assertSame($a, $b, 'Must be deterministic');
            $this->assertIsInt($a);
            $this->assertGreaterThanOrEqual(0, $a);
            $this->assertLessThanOrEqual(0xFFFFFFFF, $a);
        }
    }

    public function test_different_inputs_usually_differ(): void
    {
        $crc = new Crc32;
        $this->assertNotSame($crc->compute('foo'), $crc->compute('bar'));
        $this->assertNotSame($crc->compute('foo'), $crc->compute('foo '));
        $this->assertNotSame($crc->compute('foo'), $crc->compute('foO'));
    }

    public function test_matches_php_builtin(): void
    {
        $crc = new Crc32;

        $inputs = [
            '',
            '123456789',
            'hello world',
            random_bytes(64),
        ];

        foreach ($inputs as $data) {
            $expected = (int) sprintf('%u', crc32($data)); // PHP’s builtin CRC32
            $this->assertSame($expected, $crc->compute($data));
        }
    }
}
