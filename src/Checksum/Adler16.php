<?php

declare(strict_types=1);

namespace KDuma\PKV\Checksum;

final class Adler16 implements Checksum16Interface
{
    /**
     * Compute the Adler-16 checksum for the given binary string.
     * Mirrors the C# logic:
     *  - a starts at 1, b at 0
     *  - process in chunks of 5550
     *  - modulus 251
     *  - return (b << 8) | a (16-bit)
     */
    public function compute(string $data): int
    {
        $a = 1;
        $b = 0;
        $len = \strlen($data);
        $offset = 0;

        while ($len > 0) {
            $tlen = $len < 5550 ? $len : 5550;
            $len -= $tlen;

            do {
                $a += \ord($data[$offset++]);
                $b += $a;
            } while (--$tlen > 0);

            $a %= 251;
            $b %= 251;
        }

        // Force to 16-bit, matching C# ushort cast
        return (($b << 8) | $a) & 0xFFFF;
    }
}
