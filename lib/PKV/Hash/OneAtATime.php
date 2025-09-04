<?php
declare(strict_types=1);

namespace KDuma\PKV\Hash;

/**
 * Bob Jenkins' One-at-a-Time (OAAT) hash.
 * Ref: http://www.burtleburtle.net/bob/hash/doobs.html
 */
final class OneAtATime implements HashInterface
{
    /**
     * Compute the 32-bit OAAT hash of a binary string.
     *
     * @param string $data Binary string (equivalent to C# byte[]).
     * @return int Unsigned 32-bit integer (0..0xFFFFFFFF).
     */
    public function compute(string $data): int
    {
        $hash = 0;

        $len = \strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash + \ord($data[$i])) & 0xFFFFFFFF;
            $hash = ($hash + (($hash << 10) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $hash ^= (($hash >> 6) & 0xFFFFFFFF);
        }

        $hash = ($hash + (($hash << 3) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        $hash ^= (($hash >> 11) & 0xFFFFFFFF);
        $hash = ($hash + (($hash << 15) & 0xFFFFFFFF)) & 0xFFFFFFFF;

        // ensure unsigned 32-bit range
        return (int) \sprintf('%u', $hash);
    }
}
