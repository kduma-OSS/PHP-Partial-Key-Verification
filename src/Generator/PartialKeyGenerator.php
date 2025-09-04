<?php

declare(strict_types=1);

namespace KDuma\PKV\Generator;

use KDuma\PKV\Base32;
use KDuma\PKV\Checksum\Checksum16Interface;
use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\Hash\HashInterface;

final class PartialKeyGenerator
{
    private static ?HashInterface $defaultHash = null;

    /** @var list<int> */
    private array $baseKeys;

    private Checksum16Interface $checksum;

    /** @var list<HashInterface> */
    private array $hashFunctions;

    private int $spacing;

    /**
     * Private value-constructor. Use one of the named factories below.
     *
     * @param  list<int>  $baseKeys
     * @param  list<HashInterface>  $hashFunctions
     */
    private function __construct(
        array $baseKeys,
        Checksum16Interface $checksum,
        array $hashFunctions,
        int $spacing = 0
    ) {
        if ($baseKeys === []) {
            throw new \InvalidArgumentException('baseKeys must be non-empty');
        }
        if ($hashFunctions === []) {
            throw new \InvalidArgumentException('hashFunctions must be non-empty');
        }

        // normalize inputs
        $this->baseKeys = array_map(static fn ($v) => (int) $v, $baseKeys);
        $this->checksum = $checksum;
        $this->hashFunctions = $hashFunctions;
        $this->spacing = max(0, (int) $spacing);
    }

    /**
     * Factory: build from a KeyDefinition (clean DI path).
     */
    public static function fromKeyDefinition(KeyDefinition $def): self
    {
        return new self(
            $def->getBaseKeys(),
            $def->getChecksum(),
            $def->getHashFunctions(),
            $def->getSpacing()
        );
    }

    /**
     * Factory: single-hash overload.
     *
     * @param  list<int>  $baseKeys
     */
    public static function fromSingleHash(
        Checksum16Interface $checksum,
        HashInterface $hash,
        array $baseKeys
    ): self {
        return new self($baseKeys, $checksum, [$hash], 0);
    }

    /**
     * Factory: multiple-hashes overload.
     *
     * @param  list<HashInterface>  $hashFunctions
     * @param  list<int>  $baseKeys
     */
    public static function fromMultipleHashes(
        Checksum16Interface $checksum,
        array $hashFunctions,
        array $baseKeys
    ): self {
        return new self($baseKeys, $checksum, $hashFunctions, 0);
    }

    /** Spacing (group size for dashes); 0 = no dashes */
    public function setSpacing(int $spacing): void
    {
        $this->spacing = max(0, $spacing);
    }

    public function getSpacing(): int
    {
        return $this->spacing;
    }

    /** Generate a key from a uint32 seed. */
    public function generate(int $seed): string
    {
        $seed = $seed & 0xFFFFFFFF;

        $dataLen = (count($this->baseKeys) * 4) + 4;
        $data = str_repeat("\x00", $dataLen);

        // write seed (LE)
        $data = substr_replace($data, pack('V', $seed), 0, 4);

        // subkeys
        $hashIdx = 0;
        $offset = 4;
        $numHashes = count($this->hashFunctions);

        foreach ($this->baseKeys as $base) {
            $digit = ($seed ^ ($base & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $payload = pack('V', $digit); // LE

            $hval = $this->hashFunctions[$hashIdx]->compute($payload) & 0xFFFFFFFF;
            $data = substr_replace($data, pack('V', $hval), $offset, 4);

            $offset += 4;
            $hashIdx = ($hashIdx + 1) % $numHashes;
        }

        // checksum (LE 16-bit) over data
        $sum = $this->checksum->compute($data) & 0xFFFF;
        $keyBytes = $data.pack('v', $sum);

        // Base32 encode
        $ret = Base32::toBase32($keyBytes);

        // Insert dashes if spacing > 0
        if ($this->spacing > 0) {
            $groups = intdiv(strlen($ret), $this->spacing);
            if ((strlen($ret) % $this->spacing) === 0) {
                $groups -= 1;
            }
            for ($i = 0; $i < $groups; $i++) {
                $pos = $this->spacing + ($i * $this->spacing + $i);
                $ret = substr($ret, 0, $pos).'-'.substr($ret, $pos);
            }
        }

        return $ret;
    }

    /** Generate from a string seed (UTF-8) using FNV-1a to produce the uint32 seed. */
    public function generateFromString(string $seed): string
    {
        $hash = self::$defaultHash ??= new Fnv1a;
        $seed32 = $hash->compute($seed);

        return $this->generate($seed32);
    }

    /**
     * Generate N random keys; returns [seed => key].
     *
     * @param  \Random\Randomizer|null  $randomizer  Use a seeded engine for reproducibility
     * @return array<int,string> seed => key
     */
    public function generateMany(int $numberOfKeys, ?\Random\Randomizer $randomizer = null): array
    {
        if ($numberOfKeys < 0) {
            throw new \InvalidArgumentException('numberOfKeys must be >= 0');
        }

        $randomizer ??= new \Random\Randomizer;
        $out = [];

        while (count($out) < $numberOfKeys) {
            $seed = $randomizer->getInt(0, 0xFFFFFFFF);
            if (! array_key_exists($seed, $out)) {
                $out[$seed] = $this->generate($seed);
            }
        }

        return $out;
    }
}
