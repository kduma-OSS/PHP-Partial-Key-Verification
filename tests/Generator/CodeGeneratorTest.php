<?php

declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Generator\CodeGenerator;
use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use KDuma\PKV\Generator\KeyDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Generator\CodeGenerator::class)] final class CodeGeneratorTest extends TestCase
{
    private function makeDefinition(array $baseKeys, array $hashTypes, ChecksumType $checksum = ChecksumType::Adler16): KeyDefinition
    {
        $def = new KeyDefinition;
        $def->setBaseKeys($baseKeys);
        $def->setChecksumType($checksum);
        $def->setHashTypes($hashTypes);
        $def->setSpacing(0);
        $def->setMask(''); // not used here

        return $def;
    }

    public function test_empty_when_no_verified_keys(): void
    {
        $def = $this->makeDefinition(
            [0x11111111, 0x22222222],
            [HashType::Fnv1A, HashType::OneAtATime]
        );

        $gen = new CodeGenerator($def);
        $this->assertSame('', (string) $gen);
    }

    public function test_generates_without_username_and_with_blacklist(): void
    {
        // Arrange a definition where indices map to known classes
        // index 0 => Jenkins96
        // index 1 => OneAtATime
        // index 2 => GeneralizedCrc
        // index 3 => SuperFast
        // index 4 => Fnv1A (note: class is \KDuma\PKV\Hash\Fnv1a)
        $def = $this->makeDefinition(
            [3129109879, 766109221, 534025585, 1416678536, 4072442218],
            [HashType::Jenkins96, HashType::OneAtATime, HashType::GeneralizedCrc, HashType::SuperFast, HashType::Fnv1A],
            ChecksumType::Adler16
        );

        $gen = new CodeGenerator($def);
        $gen->setVerifiedKeys([1, 4]);                 // only indices 1 and 4 are required
        $gen->setBlacklistedSerials([1518008798, 42]); // add some blacklist content
        $gen->setValidateUsername(false);

        $code = (string) $gen;

        // Signature (no username)
        $this->assertStringContainsString('private static function validateKey(string $key): bool', $code);

        // Blacklist block
        $this->assertStringContainsString('$seed = \\KDuma\\PKV\\PartialKeyValidator::getSerialNumberFromKey($key);', $code);
        $this->assertStringContainsString('$blacklist = [1518008798, 42]', $code);
        $this->assertStringContainsString('in_array($seed, $blacklist, true)', $code);

        // Validation for index 1 -> OneAtATime with base 766109221
        $this->assertStringContainsString(
            '// Validation for key with index 1',
            $code
        );
        $this->assertStringContainsString(
            '\\KDuma\\PKV\\PartialKeyValidator::validateKey(new \\KDuma\\PKV\\Checksum\\Adler16(), new \\KDuma\\PKV\\Hash\\OneAtATime(), $key, 1, 766109221)',
            $code
        );

        // Validation for index 4 -> Fnv1a with base 4072442218
        $this->assertStringContainsString(
            '// Validation for key with index 4',
            $code
        );
        $this->assertStringContainsString(
            '\\KDuma\\PKV\\PartialKeyValidator::validateKey(new \\KDuma\\PKV\\Checksum\\Adler16(), new \\KDuma\\PKV\\Hash\\Fnv1a(), $key, 4, 4072442218)',
            $code
        );

        // No username usage in this mode
        $this->assertStringNotContainsString('$userName', $code);
        $this->assertStringContainsString('return true;', $code);
    }

    public function test_generates_with_username_and_validator_instance(): void
    {
        // Arrange a definition where we’ll check two indices
        $def = $this->makeDefinition(
            [0xAAAAAAAA, 0xBBBBBBBB, 0xCCCCCCCC],
            [HashType::Fnv1A, HashType::SuperFast, HashType::GeneralizedCrc],
            ChecksumType::Crc16
        );

        $gen = new CodeGenerator($def);
        $gen->setVerifiedKeys([0, 2]);   // verify indices 0 and 2
        $gen->setBlacklistedSerials([]); // no blacklist for this test
        $gen->setValidateUsername(true);

        $code = (string) $gen;

        // Signature (with username)
        $this->assertStringContainsString('private static function validateKey(string $userName, string $key): bool', $code);

        // Validator instance creation
        $this->assertStringContainsString('$validator = new \\KDuma\\PKV\\PartialKeyValidator(new \\KDuma\\PKV\\Hash\\Fnv1a());', $code);

        // Index 0 -> Checksum Crc16, Hash Fnv1a, base 2863311530
        $this->assertStringContainsString('// Validation for key with index 0', $code);
        $this->assertStringContainsString(
            '$validator->validateKeyWithSeedString(new \\KDuma\\PKV\\Checksum\\Crc16(), new \\KDuma\\PKV\\Hash\\Fnv1a(), $key, 0, 2863311530, $userName)',
            $code
        );

        // Index 2 -> Checksum Crc16, Hash GeneralizedCrc, base 3435973836
        $this->assertStringContainsString('// Validation for key with index 2', $code);
        $this->assertStringContainsString(
            '$validator->validateKeyWithSeedString(new \\KDuma\\PKV\\Checksum\\Crc16(), new \\KDuma\\PKV\\Hash\\GeneralizedCrc(), $key, 2, 3435973836, $userName)',
            $code
        );

        // Must end with return true
        $this->assertStringContainsString('return true;', $code);
    }

    public function test_skips_out_of_range_verified_key_indices(): void
    {
        $def = $this->makeDefinition(
            [111, 222],                         // only 2 base keys
            [HashType::Fnv1A, HashType::Crc32] // only 2 hash types
        );

        $gen = new CodeGenerator($def);
        $gen->setVerifiedKeys([0, 1, 2, 7]); // 2 and 7 are out-of-range and should be ignored
        $gen->setValidateUsername(false);

        $code = (string) $gen;

        // Should contain validations for 0 and 1
        $this->assertStringContainsString('// Validation for key with index 0', $code);
        $this->assertStringContainsString('// Validation for key with index 1', $code);

        // Should NOT contain index 2 or 7
        $this->assertStringNotContainsString('// Validation for key with index 2', $code);
        $this->assertStringNotContainsString('// Validation for key with index 7', $code);
    }
}
