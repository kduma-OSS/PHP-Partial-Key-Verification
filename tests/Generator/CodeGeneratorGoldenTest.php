<?php

declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Generator\CodeGenerator;
use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use KDuma\PKV\Generator\KeyDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Generator\CodeGenerator::class)] final class CodeGeneratorGoldenTest extends TestCase
{
    private static function normalize(string $s): string
    {
        // Normalize all line endings to "\n" for stable comparison across OSes.
        return preg_replace("/\r\n|\r/", "\n", $s) ?? $s;
    }

    private function makeDefinition(array $baseKeys, array $hashTypes, ChecksumType $checksum): KeyDefinition
    {
        $def = new KeyDefinition;
        $def->setBaseKeys($baseKeys);
        $def->setHashTypes($hashTypes);
        $def->setChecksumType($checksum);
        $def->setSpacing(0);
        $def->setMask(''); // not used for codegen

        return $def;
    }

    public function test_golden_without_username_with_blacklist(): void
    {
        // Matches the simpler scenario from earlier tests:
        // - Checksum: Adler16
        // - Hashes (by index): [Jenkins96, OneAtATime, GeneralizedCrc, SuperFast, Fnv1A]
        // - Verified indices: [1, 4]
        // - Blacklist: [1518008798, 42]
        // - Base keys at indices 0..4 below:
        $def = $this->makeDefinition(
            [3129109879, 766109221, 534025585, 1416678536, 4072442218],
            [HashType::Jenkins96, HashType::OneAtATime, HashType::GeneralizedCrc, HashType::SuperFast, HashType::Fnv1A],
            ChecksumType::Adler16
        );

        $gen = new CodeGenerator($def);
        $gen->setVerifiedKeys([1, 4]);
        $gen->setBlacklistedSerials([1518008798, 42]);
        $gen->setValidateUsername(false);

        $actual = (string) $gen;

        $expected = file_get_contents(__DIR__.'/fixtures/codegen_without_username.php.txt');
        $this->assertNotFalse($expected, 'Missing fixture: fixtures/codegen_without_username.php.txt');

        $this->assertSame(
            self::normalize($expected),
            self::normalize($actual),
            "Generated code (no-username) doesn't match golden fixture"
        );
    }

    public function test_golden_with_username_no_blacklist(): void
    {
        // - Checksum: Crc16
        // - Hashes: [Fnv1A, SuperFast, GeneralizedCrc]
        // - Verified indices: [0, 2]
        // - No blacklist
        // - Base keys: 0xAAAAAAAA, 0xBBBBBBBB, 0xCCCCCCCC
        $def = $this->makeDefinition(
            [0xAAAAAAAA, 0xBBBBBBBB, 0xCCCCCCCC],
            [HashType::Fnv1A, HashType::SuperFast, HashType::GeneralizedCrc],
            ChecksumType::Crc16
        );

        $gen = new CodeGenerator($def);
        $gen->setVerifiedKeys([0, 2]);
        $gen->setBlacklistedSerials([]);
        $gen->setValidateUsername(true);

        $actual = (string) $gen;

        $expected = file_get_contents(__DIR__.'/fixtures/codegen_with_username.php.txt');
        $this->assertNotFalse($expected, 'Missing fixture: fixtures/codegen_with_username.php.txt');

        $this->assertSame(
            self::normalize($expected),
            self::normalize($actual),
            "Generated code (with-username) doesn't match golden fixture"
        );
    }
}
