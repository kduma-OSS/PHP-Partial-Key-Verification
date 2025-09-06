<?php

declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Generator\PartialKeyGenerator;
use KDuma\PKV\Hash\Jenkins96;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Generator\CodeGenerator::class)] final class CrossPlatformCodeGeneratorTest extends TestCase
{
    public function test_empty_when_no_verified_keys(): void
    {
        $generator = PartialKeyGenerator::fromSingleHash(
            new Adler16,
            new Jenkins96(0),
            [1, 2, 3, 4]
        );

        $generator->setSpacing(6);

        $key = $generator->generateFromString('bob@smith.com');

        $this->assertSame('QDKZUO-JLLWPY-XWOULC-ONCQIN-5R5X35-ZS3KEQ', $key);
    }
}
