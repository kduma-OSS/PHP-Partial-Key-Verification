<?php

declare(strict_types=1);

namespace KDuma\PKV;

use KDuma\PKV\Checksum\Checksum16Interface;
use KDuma\PKV\Hash\HashInterface;

/**
 * Validates a Partial Key Verification key.
 * A key is valid if the checksum is valid and the requested subkey(s) are valid.
 */
final class PartialKeyValidator
{
    /** Default hash used only by the serial-from-seed helpers (mirrors C# static Fnv1A). */
    private HashInterface $defaultHash;

    public function __construct(HashInterface $defaultHash)
    {
        $this->defaultHash = $defaultHash;
    }

    /**
     * Validates the given key. Verifies the checksum and the subkey at index.
     *
     * @param  Checksum16Interface  $checksum  Algorithm to compute the key checksum (16-bit)
     * @param  HashInterface  $hash  Algorithm to compute each subkey (32-bit)
     * @param  string  $key  Base32 key string (dashes allowed)
     * @param  int  $subkeyIndex  Zero-based index of the subkey to check
     * @param  int  $subkeyBase  Unsigned 32-bit base used to create the subkey
     */
    public static function validateKey(
        Checksum16Interface $checksum,
        HashInterface $hash,
        string $key,
        int $subkeyIndex,
        int $subkeyBase
    ): bool {
        $bytes = self::getKeyBytes($key);                 // binary string
        $seed = self::u32leAt($bytes, 0);                // first 4 bytes (LE)

        return self::validateKeyBytes($hash, $checksum, $bytes, $seed, $subkeyIndex, $subkeyBase);
    }

    /**
     * Validates the given key and also verifies the provided seed string hashes
     * to the embedded seed in the key (UTF-8, default hash algorithm).
     *
     * @param  string  $seedString  UTF-8 string used to generate the seed
     */
    public function validateKeyWithSeedString(
        Checksum16Interface $checksum,
        HashInterface $hash,
        string $key,
        int $subkeyIndex,
        int $subkeyBase,
        string $seedString
    ): bool {
        $bytes = self::getKeyBytes($key);
        $seed = self::u32leAt($bytes, 0);

        if ($this->defaultHash->compute($seedString) !== $seed) {
            return false;
        }

        return self::validateKeyBytes($hash, $checksum, $bytes, $seed, $subkeyIndex, $subkeyBase);
    }

    /**
     * Extracts the serial number (seed) from a key.
     */
    public static function getSerialNumberFromKey(string $key): int
    {
        $bytes = self::getKeyBytes($key);

        return self::u32leAt($bytes, 0);
    }

    /**
     * Converts a string seed into a serial number using the provided default hash.
     * (C# used a static Fnv1A; here you pass it via the constructor.)
     */
    public function getSerialNumberFromSeed(string $seed): int
    {
        return $this->defaultHash->compute($seed);
    }

    /**
     * Convert a Base32 key string (dashes allowed) to raw bytes (binary string).
     */
    private static function getKeyBytes(string $key): string
    {
        // Remove separators and normalize to uppercase
        $clean = \strtoupper(\str_replace('-', '', $key));

        return Base32::fromBase32($clean); // binary string
    }

    /**
     * Validate the checksum and a single subkey.
     *
     * @throws \OutOfRangeException if subkey index is out of bounds
     */
    private static function validateKeyBytes(
        HashInterface $hash,
        Checksum16Interface $checksum,
        string $keyBytes,
        int $seed,
        int $subkeyIndex,
        int $subkeyBase
    ): bool {
        if (! self::validateChecksum($checksum, $keyBytes)) {
            return false;
        }

        $totalLen = \strlen($keyBytes);         // includes trailing 2-byte checksum
        $offset = ($subkeyIndex * 4) + 4;     // after 4-byte seed

        if ($subkeyIndex < 0 || ($offset + 4) > ($totalLen - 2)) {
            throw new \OutOfRangeException('Sub key index is out of bounds');
        }

        $subKey = self::u32leAt($keyBytes, $offset);
        $payload = \pack('V', ($seed ^ $subkeyBase) & 0xFFFFFFFF); // LE uint32
        $expected = $hash->compute($payload);

        return $expected === $subKey;
    }

    /**
     * Validate the 16-bit checksum at the end of the key.
     */
    private static function validateChecksum(Checksum16Interface $checksum, string $keyBytes): bool
    {
        $len = \strlen($keyBytes);
        if ($len < 2) {
            return false;
        }

        $sumStored = self::u16leAt($keyBytes, $len - 2);
        $body = \substr($keyBytes, 0, $len - 2);  // without checksum

        $sumComputed = $checksum->compute($body) & 0xFFFF;

        return $sumStored === $sumComputed;
    }

    /** Read little-endian uint16 at $offset from binary string. */
    private static function u16leAt(string $bin, int $offset): int
    {
        /** @var array{1:int} $u */
        $u = \unpack('v', \substr($bin, $offset, 2));

        return $u[1] & 0xFFFF;
    }

    /** Read little-endian uint32 at $offset from binary string. */
    private static function u32leAt(string $bin, int $offset): int
    {
        /** @var array{1:int} $u */
        $u = \unpack('V', \substr($bin, $offset, 4));

        // ensure unsigned
        return (int) \sprintf('%u', $u[1]);
    }
}
