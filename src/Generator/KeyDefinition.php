<?php

declare(strict_types=1);

namespace KDuma\PKV\Generator;

use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Checksum\Checksum16Interface;
use KDuma\PKV\Checksum\Crc16;
use KDuma\PKV\Checksum\CrcCcitt;
use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;
use KDuma\PKV\Hash\Crc32;
use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\Hash\GeneralizedCrc;
use KDuma\PKV\Hash\HashInterface;
use KDuma\PKV\Hash\Jenkins96;
use KDuma\PKV\Hash\OneAtATime;
use KDuma\PKV\Hash\SuperFast;

/**
 * Definition of how keys are generated/validated (checksum, hash functions, base keys, mask).
 */
final class KeyDefinition
{
    /** @var list<int> */
    private array $baseKeys = [];

    private ChecksumType $checksum;

    /** @var list<HashType> */
    private array $hashFunctions = [];

    private int $spacing = 0;

    private string $mask = '';

    public function __construct()
    {
        $this->baseKeys = [];
        $this->hashFunctions = [];
    }

    /** @return list<int> */
    public function getBaseKeys(): array
    {
        return $this->baseKeys;
    }

    public function setBaseKeys(array $keys): void
    {
        $this->baseKeys = array_map(fn ($k) => (int) $k, $keys);
    }

    public function getChecksumType(): ChecksumType
    {
        return $this->checksum;
    }

    public function setChecksumType(ChecksumType $checksum): void
    {
        $this->checksum = $checksum;
    }

    /** @return list<HashType> */
    public function getHashTypes(): array
    {
        return $this->hashFunctions;
    }

    public function setHashTypes(array $hashTypes): void
    {
        $this->hashFunctions = $hashTypes;
    }

    public function getSpacing(): int
    {
        return $this->spacing;
    }

    public function setSpacing(int $spacing): void
    {
        $this->spacing = $spacing;
    }

    public function getMask(): string
    {
        return $this->mask;
    }

    public function setMask(string $mask): void
    {
        $this->mask = $mask;
    }

    /**
     * Instantiate the configured checksum algorithm.
     */
    public function getChecksum(): Checksum16Interface
    {
        return match ($this->checksum) {
            ChecksumType::Adler16 => new Adler16,
            ChecksumType::Crc16 => new Crc16,
            ChecksumType::CrcCcitt => new CrcCcitt,
        };
    }

    /**
     * Instantiate the configured hash algorithms.
     *
     * @return list<HashInterface>
     */
    public function getHashFunctions(): array
    {
        return array_map(function (HashType $type): HashInterface {
            return match ($type) {
                HashType::Crc32 => new Crc32,
                HashType::Fnv1A => new Fnv1a,
                HashType::GeneralizedCrc => new GeneralizedCrc,
                HashType::Jenkins06 => throw new \OutOfRangeException('Jenkins06 not supported in this context'),
                HashType::Jenkins96 => new Jenkins96,
                HashType::OneAtATime => new OneAtATime,
                HashType::SuperFast => new SuperFast,
            };
        }, $this->hashFunctions);
    }
}
