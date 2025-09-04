<?php
declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Hash\HashInterface;
use KDuma\PKV\Hash\Jenkins96;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\Hash\Jenkins96::class)] final class Jenkins96Test extends TestCase
{
    public function testImplementsInterface(): void
    {
        $h = new Jenkins96();
        $this->assertInstanceOf(HashInterface::class, $h);
    }

    public function testDeterministicAndRange(): void
    {
        $h = new Jenkins96();
        $inputs = [
            '',
            'a',
            'abc',
            'message digest',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
            '1234567890',
            "The quick brown fox jumps over the lazy dog",
            "\x00\x00\x00\x00",
            "\xFF\xFF\xFF\xFF",
            random_bytes(17),
            random_bytes(1024),
        ];

        foreach ($inputs as $data) {
            $a = $h->compute($data);
            $b = $h->compute($data);

            $this->assertSame($a, $b, 'Must be deterministic');
            $this->assertIsInt($a);
            $this->assertGreaterThanOrEqual(0, $a);
            $this->assertLessThanOrEqual(0xFFFFFFFF, $a);
        }
    }

    public function testMatchesReferenceImplementation(): void
    {
        $h = new Jenkins96();

        $inputs = [
            '',
            'a',
            'abc',
            '123456789',
            'hello world',
            random_bytes(5),
            random_bytes(13),
            random_bytes(29),
        ];

        foreach ($inputs as $data) {
            $expected = self::refJenkins96($data);
            $actual   = $h->compute($data);
            $this->assertSame($expected, $actual, 'Mismatch vs local reference');
        }
    }

    public function testSensitivityToChanges(): void
    {
        $h = new Jenkins96();
        $this->assertNotSame($h->compute('foo'), $h->compute('bar'));
        $this->assertNotSame($h->compute('foo'), $h->compute('foo '));
        $this->assertNotSame($h->compute('foo'), $h->compute('foO'));
    }

    /**
     * Local reference implementation mirroring the PHP class exactly.
     */
    private static function refJenkins96(string $data): int
    {
        $len = \strlen($data);
        $a = 0x9E3779B9;
        $b = 0x9E3779B9;
        $c = 0;

        $i = 0;

        while ($i + 12 <= $len) {
            $a = ($a + \ord($data[$i++])) & 0xFFFFFFFF;
            $a = ($a + ((\ord($data[$i++]) << 8)  & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $a = ($a + ((\ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $a = ($a + ((\ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            $b = ($b + \ord($data[$i++])) & 0xFFFFFFFF;
            $b = ($b + ((\ord($data[$i++]) << 8)  & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $b = ($b + ((\ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $b = ($b + ((\ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            $c = ($c + \ord($data[$i++])) & 0xFFFFFFFF;
            $c = ($c + ((\ord($data[$i++]) << 8)  & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $c = ($c + ((\ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF;
            $c = ($c + ((\ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF;

            self::mix($a, $b, $c);
        }

        $c = ($c + ($len & 0xFFFFFFFF)) & 0xFFFFFFFF;

        if ($i < $len) { $a = ($a + \ord($data[$i++])) & 0xFFFFFFFF; }
        if ($i < $len) { $a = ($a + ((\ord($data[$i++]) << 8)  & 0xFFFFFFFF)) & 0xFFFFFFFF; }
        if ($i < $len) { $a = ($a + ((\ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF; }
        if ($i < $len) { $a = ($a + ((\ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF; }

        if ($i < $len) { $b = ($b + \ord($data[$i++])) & 0xFFFFFFFF; }
        if ($i < $len) { $b = ($b + ((\ord($data[$i++]) << 8)  & 0xFFFFFFFF)) & 0xFFFFFFFF; }
        if ($i < $len) { $b = ($b + ((\ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF; }
        if ($i < $len) { $b = ($b + ((\ord($data[$i++]) << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF; }

        if ($i < $len) { $c = ($c + ((\ord($data[$i++]) << 8)  & 0xFFFFFFFF)) & 0xFFFFFFFF; }
        if ($i < $len) { $c = ($c + ((\ord($data[$i++]) << 16) & 0xFFFFFFFF)) & 0xFFFFFFFF; }
        if ($i < $len) { $c = ($c + ((\ord($data[$i])  << 24) & 0xFFFFFFFF)) & 0xFFFFFFFF; }

        self::mix($a, $b, $c);

        return (int) \sprintf('%u', $c & 0xFFFFFFFF);
    }

    /** same "mix" as in the class */
    private static function mix(int &$a, int &$b, int &$c): void
    {
        $a = ($a - $b - $c) & 0xFFFFFFFF; $a ^= ($c >> 13);
        $b = ($b - $c - $a) & 0xFFFFFFFF; $b ^= (($a << 8)  & 0xFFFFFFFF);
        $c = ($c - $a - $b) & 0xFFFFFFFF; $c ^= ($b >> 13);
        $a = ($a - $b - $c) & 0xFFFFFFFF; $a ^= ($c >> 12);
        $b = ($b - $c - $a) & 0xFFFFFFFF; $b ^= (($a << 16) & 0xFFFFFFFF);
        $c = ($c - $a - $b) & 0xFFFFFFFF; $c ^= ($b >> 5);
        $a = ($a - $b - $c) & 0xFFFFFFFF; $a ^= ($c >> 3);
        $b = ($b - $c - $a) & 0xFFFFFFFF; $b ^= (($a << 10) & 0xFFFFFFFF);
        $c = ($c - $a - $b) & 0xFFFFFFFF; $c ^= ($b >> 15);
    }
}
