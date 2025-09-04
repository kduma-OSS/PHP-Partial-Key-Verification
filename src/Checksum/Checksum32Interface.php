<?php

declare(strict_types=1);

namespace KDuma\PKV\Checksum;

/**
 * Classes that implement this interface should create a 32-bit checksum
 * of a given binary string (equivalent to C# byte[]).
 */
interface Checksum32Interface
{
    /**
     * Compute a 32-bit checksum for the given binary string.
     *
     * @param  string  $data  Binary string (equivalent to byte[] in C#).
     * @return int Unsigned 32-bit checksum value (0 … 4,294,967,295).
     */
    public function compute(string $data): int;
}
