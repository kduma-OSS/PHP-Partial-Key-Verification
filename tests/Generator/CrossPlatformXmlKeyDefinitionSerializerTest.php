<?php

namespace Tests\PKV\Generator;

use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use KDuma\PKV\Generator\XmlKeyDefinitionSerializer;
use PHPUnit\Framework\TestCase;

class CrossPlatformXmlKeyDefinitionSerializerTest extends TestCase
{
    public function test_demo_pkvk()
    {
        $source_xml = file_get_contents(__DIR__.'/demo.pkvk');

        $def = XmlKeyDefinitionSerializer::loadFromFile(__DIR__.'/demo.pkvk');

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

        $serialized_xml = XmlKeyDefinitionSerializer::serialize($def);

        // Compare canonical forms (ignores formatting differences)
        $this->assertSame(
            $this->canonicalizeXml($source_xml),
            $this->canonicalizeXml($serialized_xml),
            'Serialized XML does not match expected structure/content'
        );
    }

    public function test_demo_long_pkvk(): void
    {
        $source_xml = file_get_contents(__DIR__.'/demo_long.pkvk');

        $def = XmlKeyDefinitionSerializer::loadFromFile(__DIR__.'/demo_long.pkvk');

        $this->assertSame(
            [
                3805288664, 2272613436, 609843132, 3244489209, 845101189, 339598306, 2055159233, 3519305292,
                2080575350, 1214538031, 2743296543, 2045837953, 2480531269, 1417747032, 4183406825, 3871372094,
                3800337571, 1724296759, 3431313023, 1542788746, 2648676312, 946746881, 2002795508, 3767800110,
                2167839723, 3733649278, 3497920686, 3973426099, 3025042909, 2363305399, 2119305329, 1359772841,
                2030383872, 2008357872, 4040957167, 34224714, 3705717340, 3669761604, 1869347822, 4216239048,
                41428537, 3261555846, 1724925759, 1174140799, 3834300326, 696771626, 524459238, 2970127068,
                3718059442, 2538581767, 1579471850, 1826198764, 3266835616, 2732374494, 4130428571, 1172576194,
                2593214641, 3348018740, 506673257, 845246397, 2690376970, 2346051227, 1826826763, 2259327161,
                1044919578, 161580699, 46706581, 559847084, 4170103819, 23540271, 1573988128, 3290228237,
                3463187118, 1514375375, 2443412076, 2005461448, 1048667085, 2246949605, 402629382, 2470914701,
                2491680801, 1723448609, 1570185157, 2337749006, 1929457548, 493253911, 1754935977, 620599995,
                1131186369, 3561939657, 1155211228, 1858572530, 4166860687, 3859691002, 3056034846, 118560136,
                2238108175, 69116611, 3112280798, 592262238,
            ],
            $def->getBaseKeys()
        );

        $this->assertSame(ChecksumType::Adler16, $def->getChecksumType());

        $this->assertSame(
            [
                HashType::Fnv1A, HashType::Crc32, HashType::Crc32, HashType::SuperFast, HashType::OneAtATime,
                HashType::Jenkins96, HashType::Jenkins96, HashType::Jenkins96, HashType::GeneralizedCrc, HashType::Jenkins96,
                HashType::Crc32, HashType::GeneralizedCrc, HashType::SuperFast, HashType::OneAtATime, HashType::SuperFast,
                HashType::Crc32, HashType::Crc32, HashType::OneAtATime, HashType::Jenkins96, HashType::GeneralizedCrc,
                HashType::OneAtATime, HashType::Jenkins96, HashType::Jenkins96, HashType::Crc32, HashType::GeneralizedCrc,
                HashType::OneAtATime, HashType::SuperFast, HashType::Fnv1A, HashType::SuperFast, HashType::Jenkins96,
                HashType::Crc32, HashType::Crc32, HashType::GeneralizedCrc, HashType::OneAtATime, HashType::OneAtATime,
                HashType::OneAtATime, HashType::SuperFast, HashType::SuperFast, HashType::GeneralizedCrc, HashType::Crc32,
                HashType::Jenkins96, HashType::Jenkins96, HashType::Jenkins96, HashType::Jenkins96, HashType::OneAtATime,
                HashType::Jenkins96, HashType::Jenkins96, HashType::SuperFast, HashType::Jenkins96, HashType::Crc32,
                HashType::Fnv1A, HashType::GeneralizedCrc, HashType::Crc32, HashType::GeneralizedCrc, HashType::Crc32,
                HashType::Jenkins96, HashType::Jenkins96, HashType::Jenkins96, HashType::Jenkins96, HashType::OneAtATime,
                HashType::GeneralizedCrc, HashType::Jenkins96, HashType::Jenkins96, HashType::OneAtATime, HashType::Fnv1A,
                HashType::Fnv1A, HashType::Jenkins96, HashType::Jenkins96, HashType::OneAtATime, HashType::OneAtATime,
                HashType::Fnv1A, HashType::OneAtATime, HashType::SuperFast, HashType::SuperFast, HashType::OneAtATime,
                HashType::Fnv1A, HashType::GeneralizedCrc, HashType::Jenkins96, HashType::OneAtATime, HashType::SuperFast,
                HashType::Crc32, HashType::Jenkins96, HashType::Fnv1A, HashType::GeneralizedCrc, HashType::GeneralizedCrc,
                HashType::Jenkins96, HashType::GeneralizedCrc, HashType::SuperFast, HashType::Crc32, HashType::Crc32,
                HashType::SuperFast, HashType::SuperFast, HashType::Crc32, HashType::Crc32, HashType::Crc32, HashType::Fnv1A,
                HashType::Crc32, HashType::Jenkins96, HashType::Crc32, HashType::Jenkins96,
            ],
            $def->getHashTypes()
        );

        $this->assertSame(0, $def->getSpacing());

        $this->assertSame(
            '>AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            $def->getMask()
        );

        $serialized_xml = XmlKeyDefinitionSerializer::serialize($def);

        // Compare canonical forms (ignores formatting differences)
        $this->assertSame(
            $this->canonicalizeXml($source_xml),
            $this->canonicalizeXml($serialized_xml),
            'Serialized XML does not match expected structure/content'
        );
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
