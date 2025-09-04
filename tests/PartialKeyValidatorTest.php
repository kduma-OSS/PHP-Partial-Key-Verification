<?php

declare(strict_types=1);

namespace Tests\PKV;

use KDuma\PKV\Base32;
use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Checksum\Checksum16Interface;
use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\Hash\HashInterface;
use KDuma\PKV\PartialKeyValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\PartialKeyValidator::class)] final class PartialKeyValidatorTest extends TestCase
{
    private static function makeKeyBytes(
        int $seed,
        array $subkeyBases,
        HashInterface $hash,
        Checksum16Interface $checksum
    ): string {
        // Layout: [seed:4][subkey0:4][subkey1:4]...[checksum:2]
        $buf = \pack('V', $seed);

        foreach ($subkeyBases as $base) {
            $payload = \pack('V', ($seed ^ $base) & 0xFFFFFFFF);
            $subkey = $hash->compute($payload);
            $buf .= \pack('V', $subkey);
        }

        $sum = $checksum->compute($buf) & 0xFFFF;
        $buf .= \pack('v', $sum);

        return $buf;
    }

    private static function dashify(string $s, int $group = 5): string
    {
        return \strtoupper(\implode('-', \str_split($s, $group)));
    }

    public function test_serial_helpers_and_validation(): void
    {
        $hash = new Fnv1a;           // default for seed and for subkeys
        $checksum = new Adler16;

        $seedString = 'user@example.com';
        $seed = $hash->compute($seedString);

        $bases = [0x11111111, 0x22222222, 0x33333333];
        $bytes = self::makeKeyBytes($seed, $bases, $hash, $checksum);
        $keyB32 = Base32::toBase32($bytes);

        // With dashes as well (validator must ignore them)
        $keyB32Dashed = self::dashify($keyB32, 5);

        $validator = new PartialKeyValidator($hash);

        // Serial number extraction
        $this->assertSame($seed, $validator->getSerialNumberFromSeed($seedString));
        $this->assertSame($seed, PartialKeyValidator::getSerialNumberFromKey($keyB32));
        $this->assertSame($seed, PartialKeyValidator::getSerialNumberFromKey($keyB32Dashed));

        // Validate subkeys by index for both plain and dashed keys
        foreach ([0, 1, 2] as $idx) {
            $this->assertTrue(
                PartialKeyValidator::validateKey($checksum, $hash, $keyB32, $idx, $bases[$idx]),
                "validateKey failed (plain) at index {$idx}"
            );
            $this->assertTrue(
                PartialKeyValidator::validateKey($checksum, $hash, $keyB32Dashed, $idx, $bases[$idx]),
                "validateKey failed (dashed) at index {$idx}"
            );
        }

        // Wrong base should fail
        $this->assertFalse(
            PartialKeyValidator::validateKey($checksum, $hash, $keyB32, 1, 0xAAAAAAAA)
        );

        // Seed-string variant (must match)
        $this->assertTrue(
            $validator->validateKeyWithSeedString($checksum, $hash, $keyB32, 0, $bases[0], $seedString)
        );

        // Seed-string variant (mismatch)
        $this->assertFalse(
            $validator->validateKeyWithSeedString($checksum, $hash, $keyB32, 0, $bases[0], 'wrong seed')
        );
    }

    public function test_subkey_index_bounds(): void
    {
        $hash = new Fnv1a;
        $checksum = new Adler16;

        $seed = 123456789;
        $bases = [0xAAAAAAAA]; // only one subkey
        $bytes = self::makeKeyBytes($seed, $bases, $hash, $checksum);
        $keyB32 = Base32::toBase32($bytes);

        $this->expectException(\OutOfRangeException::class);
        PartialKeyValidator::validateKey($checksum, $hash, $keyB32, 1, 0xBBBBBBBB); // index 1 out-of-bounds
    }
}
