<?php

declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Generator\PartialKeyGenerator;
use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\Hash\Jenkins96;
use KDuma\PKV\PartialKeyValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Generator\CodeGenerator::class)] final class CrossPlatformCodeGeneratorTest extends TestCase
{
    public function test_cross_platform_code(): void
    {
        $generator = PartialKeyGenerator::fromSingleHash(
            new Adler16,
            new Jenkins96(0),
            [1, 2, 3, 4]
        );

        $generator->setSpacing(6);

        $key = $generator->generateFromString('bob@smith.com');

        $seed = new Fnv1a()->compute('bob@smith.com');
        $seed_from_key = PartialKeyValidator::getSerialNumberFromKey($key);

        $this->assertSame('QDKZUO-JLLWPY-XWOULC-ONCQIN-5R5X35-ZS3KEQ', $key);
        $this->assertSame(966448512, $seed);
        $this->assertSame(966448512, $seed_from_key);
    }
}
