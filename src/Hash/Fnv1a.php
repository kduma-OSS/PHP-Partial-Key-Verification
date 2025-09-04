<?php

declare(strict_types=1);

namespace KDuma\PKV\Hash;

/**
 * Implementation of the FNV-1a 32-bit hash function.
 * See: http://isthe.com/chongo/tech/comp/fnv/
 */
final class Fnv1a implements HashInterface
{
    /**
     * Compute a 32-bit FNV-1a hash of the given binary string.
     *
     * @param  string  $data  Binary string (like C# byte[]).
     * @return int Unsigned 32-bit integer (0 … 0xFFFFFFFF).
     */
    public function compute(string $data): int
    {
        $hval = 0x811C9DC5; // 2166136261
        $prime = 0x01000193; // 16777619

        $len = \strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $hval ^= \ord($data[$i]);
            // 32-bit overflow-safe multiply by FNV prime
            $hval = ($hval * $prime) & 0xFFFFFFFF;
        }

        // Ensure unsigned range
        return (int) sprintf('%u', $hval);
    }
}
