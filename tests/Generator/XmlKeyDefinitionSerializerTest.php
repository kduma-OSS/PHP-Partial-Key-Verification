<?php

declare(strict_types=1);

namespace Tests\PKV\Generator;

use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use KDuma\PKV\Generator\KeyDefinition;
use KDuma\PKV\Generator\XmlKeyDefinitionSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Generator\XmlKeyDefinitionSerializer::class)] final class XmlKeyDefinitionSerializerTest extends TestCase
{
    private const SAMPLE_XML = <<<'XML'
<?xml version="1.0"?>
<KeyDefinition xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <BaseKeys>
    <unsignedInt>3129109879</unsignedInt>
    <unsignedInt>766109221</unsignedInt>
    <unsignedInt>534025585</unsignedInt>
    <unsignedInt>1416678536</unsignedInt>
    <unsignedInt>4072442218</unsignedInt>
  </BaseKeys>
  <Checksum>Adler16</Checksum>
  <HashFunctions>
    <HashType>Jenkins96</HashType>
    <HashType>OneAtATime</HashType>
    <HashType>OneAtATime</HashType>
    <HashType>GeneralizedCrc</HashType>
    <HashType>SuperFast</HashType>
  </HashFunctions>
  <Spacing>6</Spacing>
  <Mask>&gt;AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA</Mask>
</KeyDefinition>
XML;

    public function test_deserialize_sample_xml(): void
    {
        $def = XmlKeyDefinitionSerializer::deserialize(self::SAMPLE_XML);

        $this->assertSame(
            [3129109879, 766109221, 534025585, 1416678536, 4072442218],
            $def->getBaseKeys()
        );
        $this->assertSame(ChecksumType::Adler16, $def->getChecksumType());
        $this->assertSame(
            [HashType::Jenkins96, HashType::OneAtATime, HashType::OneAtATime, HashType::GeneralizedCrc, HashType::SuperFast],
            $def->getHashTypes()
        );
        $this->assertSame(6, $def->getSpacing());
        $this->assertSame('>AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA', $def->getMask());
    }

    public function test_serialize_matches_shape_and_round_trips(): void
    {
        // Build definition matching SAMPLE_XML
        $def = new KeyDefinition;
        $def->setBaseKeys([3129109879, 766109221, 534025585, 1416678536, 4072442218]);
        $def->setChecksumType(ChecksumType::Adler16);
        $def->setHashTypes([
            HashType::Jenkins96,
            HashType::OneAtATime,
            HashType::OneAtATime,
            HashType::GeneralizedCrc,
            HashType::SuperFast,
        ]);
        $def->setSpacing(6);
        $def->setMask('>AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA-AAAAAA');

        $xml = XmlKeyDefinitionSerializer::serialize($def);

        // Compare canonical forms (ignores formatting differences)
        $this->assertSame(
            $this->canonicalizeXml(self::SAMPLE_XML),
            $this->canonicalizeXml($xml),
            'Serialized XML does not match expected structure/content'
        );

        // Round-trip
        $back = XmlKeyDefinitionSerializer::deserialize($xml);
        $this->assertSame($def->getBaseKeys(), $back->getBaseKeys());
        $this->assertSame($def->getChecksumType(), $back->getChecksumType());
        $this->assertSame($def->getHashTypes(), $back->getHashTypes());
        $this->assertSame($def->getSpacing(), $back->getSpacing());
        $this->assertSame($def->getMask(), $back->getMask());
    }

    public function test_deserialize_rejects_bad_root(): void
    {
        $this->expectException(\RuntimeException::class);
        XmlKeyDefinitionSerializer::deserialize('<nope/>');
    }

    public function test_deserialize_rejects_bad_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        XmlKeyDefinitionSerializer::deserialize('<KeyDefinition>');
    }

    private function canonicalizeXml(string $xml): string
    {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        // C14N normalizes namespaces, attribute ordering, whitespace, etc.
        return $dom->C14N() ?: '';
    }
}
