<?php

declare(strict_types=1);

namespace KDuma\PKV\Generator;

use KDuma\PKV\Generator\Enums\ChecksumType;
use KDuma\PKV\Generator\Enums\HashType;

/**
 * Generates a PHP method that validates a key using the provided KeyDefinition.
 *
 * - When $validateUsername=true, the generated code will:
 *   - create "$validator = new \KDuma\PKV\PartialKeyValidator(new \KDuma\PKV\Hash\Fnv1a());"
 *   - call $validator->validateKeyWithSeedString(new <Checksum>(), new <Hash>(), $key, $index, $base, $userName)
 *
 * - When $validateUsername=false, the generated code will:
 *   - call \KDuma\PKV\PartialKeyValidator::validateKey(new <Checksum>(), new <Hash>(), $key, $index, $base)
 *
 * If no verified keys are configured, __toString() returns an empty string.
 */
final class CodeGenerator
{
    private bool $validateUsername = false;

    private KeyDefinition $definition;

    /** @var list<int> */
    private array $verifiedKeys = [];

    /** @var list<int> */
    private array $blacklistedSerials = [];

    public function __construct(KeyDefinition $definition)
    {
        $this->definition = $definition;
        $this->verifiedKeys = [];
        $this->blacklistedSerials = [];
    }

    public function setValidateUsername(bool $validate): void
    {
        $this->validateUsername = $validate;
    }

    public function getValidateUsername(): bool
    {
        return $this->validateUsername;
    }

    public function getDefinition(): KeyDefinition
    {
        return $this->definition;
    }

    /** @param iterable<int> $enabledKeys */
    public function setVerifiedKeys(iterable $enabledKeys): void
    {
        $this->verifiedKeys = [];
        foreach ($enabledKeys as $k) {
            $this->verifiedKeys[] = (int) $k;
        }
    }

    /** @param iterable<int> $serials */
    public function setBlacklistedSerials(iterable $serials): void
    {
        $this->blacklistedSerials = [];
        foreach ($serials as $s) {
            // keep unsigned decimal representation
            $this->blacklistedSerials[] = (int) sprintf('%u', (int) $s);
        }
    }

    /** @return list<int> */
    public function getBlacklistedSerials(): array
    {
        return $this->blacklistedSerials;
    }

    /**
     * Render the PHP validation method snippet.
     * Returns an empty string when there are no verified keys.
     */
    public function __toString(): string
    {
        if (count($this->verifiedKeys) === 0) {
            return '';
        }

        $checksumClass = $this->mapChecksumClass($this->definition->getChecksumType());
        $hashTypes = $this->definition->getHashTypes();   // list<HashType>
        $baseKeys = $this->definition->getBaseKeys();    // list<int>

        $nl = "\r\n";
        $tab = "\t";
        $code = '';

        // Method signature
        $sigParams = $this->validateUsername
            ? 'string $userName, string $key'
            : 'string $key';

        $code .= "private static function validateKey({$sigParams}): bool {{$nl}";

        // Optional: validator instance when username validation is enabled
        if ($this->validateUsername) {
            $code .= "{$tab}\$validator = new \\KDuma\\PKV\\PartialKeyValidator(new \\KDuma\\PKV\\Hash\\Fnv1a());{$nl}";
            $code .= "{$nl}";
        }

        // Blacklist block (if any)
        if (count($this->blacklistedSerials) > 0) {
            $code .= "{$tab}\$seed = \\KDuma\\PKV\\PartialKeyValidator::getSerialNumberFromKey(\$key);{$nl}";
            $code .= "{$tab}\$blacklist = [";
            foreach ($this->blacklistedSerials as $serial) {
                $code .= sprintf('%s, ', (string) $serial);
            }
            $code = rtrim($code, ' ,');
            $code .= "];{$nl}";
            $code .= "{$tab}if (in_array(\$seed, \$blacklist, true)){$nl}";
            $code .= "{$tab}{$tab}return false;{$nl}";
            $code .= "{$nl}";
        }

        // Per-verified key checks
        foreach ($this->verifiedKeys as $keyIndex) {
            // guard against out-of-range indices
            if (! array_key_exists($keyIndex, $hashTypes) || ! array_key_exists($keyIndex, $baseKeys)) {
                // Skip silently (or throw if you prefer strict failure)
                continue;
            }

            $hashClass = $this->mapHashClass($hashTypes[$keyIndex]);
            $base = (int) sprintf('%u', $baseKeys[$keyIndex]);

            $code .= sprintf(
                "{$tab}// Validation for key with index %d{$nl}",
                $keyIndex
            );

            if ($this->validateUsername) {
                $code .= sprintf(
                    "{$tab}if (!\$validator->validateKeyWithSeedString(new %s(), new %s(), \$key, %d, %s, \$userName)){$nl}",
                    $checksumClass,
                    $hashClass,
                    $keyIndex,
                    (string) $base
                );
            } else {
                $code .= sprintf(
                    "{$tab}if (!\\KDuma\\PKV\\PartialKeyValidator::validateKey(new %s(), new %s(), \$key, %d, %s)){$nl}",
                    $checksumClass,
                    $hashClass,
                    $keyIndex,
                    (string) $base
                );
            }

            $code .= "{$tab}{$tab}return false;{$nl}";
            $code .= "{$nl}";
        }

        $code .= "{$tab}return true;{$nl}";
        $code .= "}{$nl}";

        return $code;
    }

    private function mapChecksumClass(ChecksumType $type): string
    {
        return match ($type) {
            ChecksumType::Adler16 => '\\KDuma\\PKV\\Checksum\\Adler16',
            ChecksumType::Crc16 => '\\KDuma\\PKV\\Checksum\\Crc16',
            ChecksumType::CrcCcitt => '\\KDuma\\PKV\\Checksum\\CrcCcitt',
        };
    }

    private function mapHashClass(HashType $type): string
    {
        return match ($type) {
            HashType::Crc32 => '\\KDuma\\PKV\\Hash\\Crc32',
            HashType::Fnv1A => '\\KDuma\\PKV\\Hash\\Fnv1a',       // note: class is Fnv1a
            HashType::GeneralizedCrc => '\\KDuma\\PKV\\Hash\\GeneralizedCrc',
            HashType::Jenkins06 => '\\KDuma\\PKV\\Hash\\Jenkins06',
            HashType::Jenkins96 => '\\KDuma\\PKV\\Hash\\Jenkins96',
            HashType::OneAtATime => '\\KDuma\\PKV\\Hash\\OneAtATime',
            HashType::SuperFast => '\\KDuma\\PKV\\Hash\\SuperFast',
        };
    }
}
