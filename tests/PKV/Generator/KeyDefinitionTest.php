<?php
declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Generator\KeyDefinition;
use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Hash\Fnv1a;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Generator\KeyDefinition::class)] final class KeyDefinitionTest extends TestCase
{
    public function testChecksumFactory(): void
    {
        $def = new KeyDefinition();
        $def->setChecksumType(ChecksumType::Adler16);

        $checksum = $def->getChecksum();
        $this->assertInstanceOf(Adler16::class, $checksum);
    }

    public function testHashFactory(): void
    {
        $def = new KeyDefinition();
        $def->setHashTypes([HashType::Fnv1A]);

        $hashes = $def->getHashFunctions();
        $this->assertCount(1, $hashes);
        $this->assertInstanceOf(Fnv1a::class, $hashes[0]);
    }

    public function testBaseKeysMaskAndSpacing(): void
    {
        $def = new KeyDefinition();
        $def->setBaseKeys([1, 2, 3]);
        $def->setSpacing(5);
        $def->setMask('XXXXX-YYYYY');

        $this->assertSame([1, 2, 3], $def->getBaseKeys());
        $this->assertSame(5, $def->getSpacing());
        $this->assertSame('XXXXX-YYYYY', $def->getMask());
    }

    public function testUnsupportedJenkins06Throws(): void
    {
        $def = new KeyDefinition();
        $def->setHashTypes([HashType::Jenkins06]);

        $this->expectException(\OutOfRangeException::class);
        $def->getHashFunctions();
    }
}
