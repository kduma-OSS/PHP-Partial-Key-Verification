<?php

declare(strict_types=1);

namespace Tests\PKV;

use KDuma\PKV\Base32;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Base32Test extends TestCase
{
    public function test_empty(): void
    {
        $this->assertSame('', Base32::toBase32(''));
        $this->assertSame('', Base32::fromBase32(''));
    }

    /**
     * RFC 4648 test vectors (UPPERCASE, no padding) adapted to unpadded form.
     * "foobar" => "MZXW6YTBOI"
     *
     * @return array<string, array{plain: string, base32: string}>
     */
    public static function vectors(): array
    {
        return [
            'f' => ['plain' => 'f',       'base32' => 'MY'],
            'fo' => ['plain' => 'fo',      'base32' => 'MZXQ'],
            'foo' => ['plain' => 'foo',     'base32' => 'MZXW6'],
            'foob' => ['plain' => 'foob',    'base32' => 'MZXW6YQ'],
            'fooba' => ['plain' => 'fooba',   'base32' => 'MZXW6YTB'],
            'foobar' => ['plain' => 'foobar',  'base32' => 'MZXW6YTBOI'],
            // add a few extra mixed-length cases
            'A' => ['plain' => 'A',       'base32' => 'IE'],
            'AB' => ['plain' => 'AB',      'base32' => 'IFBA'],
            'ABC' => ['plain' => 'ABC',     'base32' => 'IFBEG'],
        ];
    }

    #[DataProvider('vectors')]
    public function test_to_base32_matches_known_vectors(string $plain, string $base32): void
    {
        $this->assertSame($base32, Base32::toBase32($plain));
    }

    #[DataProvider('vectors')]
    public function test_from_base32_matches_known_vectors(string $plain, string $base32): void
    {
        $this->assertSame($plain, Base32::fromBase32($base32));
    }

    public function test_round_trip_random_binary(): void
    {
        // try a variety of sizes to exercise all offset branches
        $lengths = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 16, 31, 32, 33, 64, 100, 256, 1024];

        foreach ($lengths as $len) {
            $bin = ($len === 0) ? '' : random_bytes($len);
            $encoded = Base32::toBase32($bin);
            $decoded = Base32::fromBase32($encoded);

            $this->assertSame($bin, $decoded, "Failed round-trip at length {$len}");
        }
    }

    public function test_decode_then_encode_is_idempotent_for_valid_alphabet(): void
    {
        // Build a valid (unpadded) base32 string and check encode(decode(x)) == x
        $original = 'MZXW6YTBOI'; // "foobar"
        $decoded = Base32::fromBase32($original);
        $reEncoded = Base32::toBase32($decoded);

        $this->assertSame($original, $reEncoded);
    }

    public function test_case_sensitivity_uppercase_only(): void
    {
        // Implementation uses uppercase alphabet; lowercase should not be accepted
        // (original C# also lacks validation, so strpos() would return false/-1 and produce garbage).
        // Here we simply assert that lowercase does NOT decode to the valid bytes of "foobar".
        $lower = 'mzxw6ytboi';
        $decodedLower = Base32::fromBase32($lower);
        $this->assertNotSame('foobar', $decodedLower);
    }
}
