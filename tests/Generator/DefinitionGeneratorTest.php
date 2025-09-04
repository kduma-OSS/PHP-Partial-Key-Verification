<?php

declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Generator\DefinitionGenerator;
use KDuma\PKV\Generator\PartialKeyGenerator;
use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\PartialKeyValidator;
use PHPUnit\Framework\TestCase;

final class DefinitionGeneratorTest extends TestCase
{
    public function test_make_definition_mask_and_spacing_and_validation(): void
    {
        // create a definition for, say, 12 subkeys
        $def = DefinitionGenerator::makeDefinition(12);

        // Raw (no-dash) key length must equal the number of 'A' in the mask
        $genNoDash = PartialKeyGenerator::fromKeyDefinition($def);
        $genNoDash->setSpacing(0);
        $rawKey = $genNoDash->generate(0); // any seed
        $aCount = substr_count($def->getMask(), 'A');
        $this->assertSame($aCount, \strlen($rawKey), 'Mask A-count must equal raw Base32 length');

        // If spacing > 0, all groups except possibly the last must equal spacing
        $spacing = $def->getSpacing();
        if ($spacing > 0) {
            $groups = explode('-', ltrim($def->getMask(), '>'));
            for ($i = 0; $i < count($groups) - 1; $i++) {
                $this->assertSame($spacing, \strlen($groups[$i]));
            }
            $this->assertGreaterThan(0, \strlen(end($groups)));
            $this->assertLessThanOrEqual($spacing, \strlen(end($groups)));
        } else {
            // when very long, spacing can be 0 (no grouping): mask contains no dashes
            $this->assertStringNotContainsString('-', $def->getMask());
        }

        // The generated definition should produce a key; verify seed mapping is consistent
        $gen = PartialKeyGenerator::fromKeyDefinition($def);
        $key = $gen->generateFromString('demo@example.com');

        $seed = PartialKeyValidator::getSerialNumberFromKey($key);
        $this->assertSame((new Fnv1a)->compute('demo@example.com'), $seed);
    }
}
