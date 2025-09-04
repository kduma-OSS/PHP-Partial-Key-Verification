<?php

declare(strict_types=1);

namespace KDuma\PKV\Generator;

use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;

/**
 * Builds random KeyDefinition instances and derives optimal spacing/mask.
 */
final class DefinitionGenerator
{
    /**
     * Create a KeyDefinition with random checksum/hash selections and base keys.
     * Spacing and Mask are computed based on the generated key length.
     */
    public static function makeDefinition(int $numberOfKeys): KeyDefinition
    {
        $definition = new KeyDefinition;

        $definition->setChecksumType(self::getRandomChecksumFunction());
        $hashes = [];
        $baseKeys = [];

        for ($i = 0; $i < $numberOfKeys; $i++) {
            $baseKeys[] = self::getRandomUint();
            $hashes[] = self::getRandomHashFunction();
        }

        $definition->setBaseKeys($baseKeys);
        $definition->setHashTypes($hashes);

        $definition->setSpacing(self::calculateOptimalSpacing($definition));
        $definition->setMask(self::makeMask($definition));

        return $definition;
    }

    /**
     * Build the visual mask (e.g., ">AAAAA-AAAAA-...") based on spacing and generated code length.
     */
    private static function makeMask(KeyDefinition $definition): string
    {
        $mask = '>';

        // Generate a sample key with spacing = 0 to measure raw code length
        $generator = PartialKeyGenerator::fromKeyDefinition($definition);
        $generator->setSpacing(0);
        $codeLength = \strlen($generator->generate(0));

        $spacing = $definition->getSpacing();
        if ($spacing === 0) {
            return $mask.str_repeat('A', $codeLength);
        }

        $remaining = $codeLength;
        while ($remaining > 0) {
            $take = min($spacing, $remaining);
            $mask .= str_repeat('A', $take);

            $remaining -= $take;
            if ($remaining > 0) {
                $mask .= '-';
            }
        }

        return $mask;
    }

    /**
     * Choose a spacing that makes chunks relatively even and human-readable.
     * Returns 0 (no grouping) for very long codes.
     */
    private static function calculateOptimalSpacing(KeyDefinition $definition): int
    {
        $generator = PartialKeyGenerator::fromKeyDefinition($definition);
        $codeLength = \strlen($generator->generate(0));

        $min = 0;
        $max = 0;

        if ($codeLength < 30) {
            $min = 4;
            $max = 9;
        } elseif ($codeLength < 45) {
            $min = 6;
            $max = 10;
        } elseif ($codeLength < 60) {
            $min = 8;
            $max = 15;
        } elseif ($codeLength < 85) {
            $min = 10;
            $max = 20;
        } else {
            return 0; // too long—don’t group
        }

        // If perfectly divisible by any candidate, pick it immediately
        for ($opt = $min; $opt <= $max; $opt++) {
            if ($codeLength % $opt === 0) {
                return $opt;
            }
        }

        // Otherwise pick the one minimizing leftover, then the smaller group size
        $scores = []; // groupSize => score
        for ($opt = $min; $opt <= $max; $opt++) {
            // Equivalent to Math.Abs(codeLength % opt - opt)
            $scores[$opt] = abs(($codeLength % $opt) - $opt);
        }

        asort($scores, SORT_NUMERIC); // sort by score asc, then by key asc (PHP preserves key order)

        return (int) array_key_first($scores);
    }

    /**
     * Random hash type (excluding Jenkins06, as in original C#).
     */
    private static function getRandomHashFunction(): HashType
    {
        $cases = HashType::cases();

        // Filter out Jenkins06 to mirror the original behavior
        $cases = array_values(array_filter($cases, fn (HashType $t) => $t !== HashType::Jenkins06));

        $idx = random_int(0, count($cases) - 1);

        return $cases[$idx];
    }

    /**
     * Random checksum type.
     */
    private static function getRandomChecksumFunction(): ChecksumType
    {
        $cases = ChecksumType::cases();
        $idx = random_int(0, count($cases) - 1);

        return $cases[$idx];
    }

    /**
     * Random uint32 (0..0xFFFFFFFF), safe across platforms.
     */
    private static function getRandomUint(): int
    {
        // Compose from two 16-bit randoms to avoid platform int-size limits
        $hi = random_int(0, 0xFFFF);
        $lo = random_int(0, 0xFFFF);
        $u32 = (($hi << 16) | $lo) & 0xFFFFFFFF;

        // Ensure unsigned semantics
        return (int) sprintf('%u', $u32);
    }
}
