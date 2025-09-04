<?php

declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use KDuma\PKV\Generator\KeyDefinition;
use KDuma\PKV\Generator\PartialKeyGenerator;
use KDuma\PKV\Hash\Crc32;
use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\Hash\OneAtATime;
use KDuma\PKV\Hash\SuperFast;
use KDuma\PKV\PartialKeyValidator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class PartialKeyGeneratorTest extends TestCase
{
    private function makeDefinition(array $baseKeys, array $hashTypes, int $spacing = 0): KeyDefinition
    {
        $def = new KeyDefinition;
        $def->setBaseKeys($baseKeys);
        $def->setChecksumType(ChecksumType::Adler16);
        $def->setHashTypes($hashTypes);
        $def->setSpacing($spacing);

        return $def;
    }

    public function test_generate_and_validate_with_numeric_seed(): void
    {
        $def = $this->makeDefinition(
            [0x11111111, 0x22222222, 0x33333333],
            [HashType::Fnv1A, HashType::OneAtATime, HashType::SuperFast],
            0
        );

        $gen = PartialKeyGenerator::fromKeyDefinition($def);
        $key = $gen->generate(0xCAFEBABE);

        // Validate subkeys via validator
        $this->assertTrue(PartialKeyValidator::validateKey(new Adler16, new \KDuma\PKV\Hash\Fnv1a, $key, 0, 0x11111111));
        $this->assertTrue(PartialKeyValidator::validateKey(new Adler16, new OneAtATime, $key, 1, 0x22222222));
        $this->assertTrue(PartialKeyValidator::validateKey(new Adler16, new SuperFast, $key, 2, 0x33333333));
    }

    public function test_generate_from_string_seed_matches_validator_seed_string_path(): void
    {
        $def = $this->makeDefinition(
            [0xAAAAAAAA, 0xBBBBBBBB],
            [HashType::Fnv1A, HashType::Fnv1A],
            5
        );

        $gen = PartialKeyGenerator::fromKeyDefinition($def);
        $seedString = 'user@example.com';
        $key = $gen->generateFromString($seedString);

        $validator = new \KDuma\PKV\PartialKeyValidator(new Fnv1a);
        $this->assertTrue($validator->validateKeyWithSeedString(
            new Adler16, new Fnv1a, $key, 0, 0xAAAAAAAA, $seedString
        ));
        $this->assertTrue($validator->validateKeyWithSeedString(
            new Adler16, new Fnv1a, $key, 1, 0xBBBBBBBB, $seedString
        ));

        // Check spacing (groups of 5 separated by dashes)
        $this->assertMatchesRegularExpression('/^([A-Z2-7]{5}-)*[A-Z2-7]{1,5}$/', $key);
    }

    public function test_generate_many_unique_and_valid(): void
    {
        $def = $this->makeDefinition(
            [0x01020304, 0xA5A5A5A5, 0x0BADF00D, 0xFEEDFACE],
            [HashType::Crc32, HashType::Fnv1A],
            0
        );

        $gen = PartialKeyGenerator::fromKeyDefinition($def);

        // reproducible engine
        $rand = new Randomizer(new Mt19937(123456));
        $keys = $gen->generateMany(20, $rand);

        $this->assertCount(20, $keys);
        $this->assertSame(array_keys($keys), array_unique(array_keys($keys)), 'Seeds must be unique');

        // spot-validate a few entries using subkey checks
        $i = 0;
        foreach ($keys as $seed => $key) {
            if ($i++ >= 5) {
                break;
            }
            $this->assertTrue(
                PartialKeyValidator::validateKey(new Adler16, new Crc32, $key, 0, 0x01020304)
            );
        }
    }

    public function test_alternate_factories_single_and_multiple(): void
    {
        $checksum = new Adler16;
        $baseKeys = [0x11111111, 0x22222222];

        // single-hash
        $gen1 = PartialKeyGenerator::fromSingleHash($checksum, new Fnv1a, $baseKeys);
        $k1 = $gen1->generate(0x12345678);
        $this->assertIsString($k1);

        // multiple-hash
        $gen2 = PartialKeyGenerator::fromMultipleHashes($checksum, [new Fnv1a, new OneAtATime], $baseKeys);
        $k2 = $gen2->generate(0x12345678);
        $this->assertIsString($k2);

        $this->assertNotSame($k1, $k2, 'Different hash sets should typically yield different keys');
    }
}
