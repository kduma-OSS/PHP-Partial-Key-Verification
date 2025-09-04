<?php

declare(strict_types=1);

namespace KDuma\PKV\Checksum;

/**
 * Classes that implement this interface should create a 16-bit checksum
 * of a given binary string (equivalent to C# byte[]).
 */
interface Checksum16Interface
{
    /**
     * Compute a 16-bit checksum for the given binary string.
     *
     * @param  string  $data  Binary string (equivalent to byte[] in C#).
     * @return int Unsigned 16-bit checksum value (0 … 65535).
     */
    public function compute(string $data): int;
}
