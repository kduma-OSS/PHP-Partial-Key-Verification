<?php

declare(strict_types=1);

namespace KDuma\PKV\Generator;

use DOMDocument;
use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use RuntimeException;

final class XmlKeyDefinitionSerializer
{
    /**
     * Serialize a KeyDefinition into the requested XML format.
     */
    public static function serialize(KeyDefinition $def): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('KeyDefinition');
        // match the .NET serializer default namespace declarations
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $doc->appendChild($root);

        // <BaseKeys><unsignedInt>...</unsignedInt>...</BaseKeys>
        $baseKeysEl = $doc->createElement('BaseKeys');
        foreach ($def->getBaseKeys() as $k) {
            // ensure unsigned string representation
            $unsigned = sprintf('%u', $k & 0xFFFFFFFF);
            $baseKeysEl->appendChild($doc->createElement('unsignedInt', $unsigned));
        }
        $root->appendChild($baseKeysEl);

        // <Checksum>Adler16</Checksum>
        $root->appendChild($doc->createElement('Checksum', $def->getChecksumType()->name));

        // <HashFunctions><HashType>...</HashType>...</HashFunctions>
        $hashFnsEl = $doc->createElement('HashFunctions');
        foreach ($def->getHashTypes() as $type) {
            $hashFnsEl->appendChild($doc->createElement('HashType', $type->name));
        }
        $root->appendChild($hashFnsEl);

        // <Spacing>6</Spacing>
        $root->appendChild($doc->createElement('Spacing', (string) $def->getSpacing()));

        // <Mask>...</Mask> (DOM handles escaping, e.g. leading '>' becomes &gt;)
        $root->appendChild($doc->createElement('Mask', $def->getMask()));

        return $doc->saveXML() ?: '';
    }

    /**
     * Parse the XML and build a KeyDefinition instance.
     *
     * @throws RuntimeException on malformed XML
     */
    public static function deserialize(string $xml): KeyDefinition
    {
        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = false;
        $ok = @$doc->loadXML($xml);
        if (! $ok) {
            throw new RuntimeException('Invalid XML for KeyDefinition');
        }

        $root = $doc->documentElement;
        if (! $root || $root->nodeName !== 'KeyDefinition') {
            throw new RuntimeException('Missing <KeyDefinition> root element');
        }

        $def = new KeyDefinition;

        // BaseKeys
        $base = [];
        $baseKeys = $root->getElementsByTagName('BaseKeys')->item(0);
        if ($baseKeys) {
            /** @var \DOMElement $child */
            foreach ($baseKeys->getElementsByTagName('unsignedInt') as $child) {
                $val = trim($child->textContent);
                // handle >32-bit safely on 64-bit PHP; keep unsigned string semantics
                $base[] = (int) sprintf('%u', (int) $val);
            }
        }
        $def->setBaseKeys($base);

        // Checksum
        $checksumEl = $root->getElementsByTagName('Checksum')->item(0);
        if (! $checksumEl) {
            throw new RuntimeException('Missing <Checksum>');
        }
        $checksumType = trim($checksumEl->textContent);
        $def->setChecksumType(ChecksumType::from($checksumType));

        // HashFunctions
        $hashTypes = [];
        $hashFnsEl = $root->getElementsByTagName('HashFunctions')->item(0);
        if ($hashFnsEl) {
            /** @var \DOMElement $child */
            foreach ($hashFnsEl->getElementsByTagName('HashType') as $child) {
                $hashTypes[] = HashType::from(trim($child->textContent));
            }
        }
        $def->setHashTypes($hashTypes);

        // Spacing
        $spacingEl = $root->getElementsByTagName('Spacing')->item(0);
        $def->setSpacing($spacingEl ? (int) trim($spacingEl->textContent) : 0);

        // Mask
        $maskEl = $root->getElementsByTagName('Mask')->item(0);
        $def->setMask($maskEl ? $maskEl->textContent : '');

        return $def;
    }

    /**
     * Save a KeyDefinition to an XML file (UTF-8).
     */
    public static function saveToFile(string $file, KeyDefinition $def): void
    {
        $xml = self::serialize($def);

        // optional: atomic-ish write via temp file + rename
        $dir = \dirname($file);
        if (! is_dir($dir)) {
            throw new \RuntimeException("Directory does not exist: {$dir}");
        }

        $tmp = tempnam($dir, 'pkvk_');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temporary file for write');
        }

        try {
            if (file_put_contents($tmp, $xml) === false) {
                throw new \RuntimeException("Failed writing XML to temp file: {$tmp}");
            }
            if (! @rename($tmp, $file)) {
                // fallback if cross-filesystem
                if (! @copy($tmp, $file) || ! @unlink($tmp)) {
                    throw new \RuntimeException("Failed moving temp file into place: {$file}");
                }
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Load a KeyDefinition from an XML file.
     */
    public static function loadFromFile(string $file): KeyDefinition
    {
        if (! is_file($file)) {
            throw new \RuntimeException("File not found: {$file}");
        }
        $xml = file_get_contents($file);
        if ($xml === false) {
            throw new \RuntimeException("Failed reading XML from file: {$file}");
        }

        return self::deserialize($xml);
    }
}
