<?php

declare(strict_types=1);

namespace KDuma\PKV\Hash;

/**
 * Classes that implement this interface should create a 32-bit hash
 * of the given binary string.
 */
interface HashInterface
{
    /**
     * Compute a 32-bit hash of the given binary string.
     *
     * @param  string  $data  Binary string (equivalent to byte[] in C#).
     * @return int Unsigned 32-bit hash value (0 … 4,294,967,295).
     */
    public function compute(string $data): int;
}
