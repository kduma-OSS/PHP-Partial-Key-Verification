<?php

declare(strict_types=1);

namespace Tests\PKV\Checksum;

use KDuma\PKV\Checksum\Checksum32Interface;
use PHPUnit\Framework\TestCase;

final class Checksum32InterfaceTest extends TestCase
{
    /**
     * @var class-string<Checksum32Interface>[]
     */
    private array $implementations = [
        \KDuma\PKV\Hash\Crc32::class,
    ];

    public function test_compute_returns_unsigned32bit_int(): void
    {
        $samples = [
            '',
            'a',
            'abc',
            'foobar',
            "\x00",
            "\x00\xFF\x01\x02",
            str_repeat('xyz', 100),
            random_bytes(64),
        ];

        foreach ($this->implementations as $class) {
            /** @var Checksum32Interface $algo */
            $algo = new $class;

            foreach ($samples as $data) {
                $out = $algo->compute($data);

                $this->assertIsInt($out, "{$class}::compute() must return int");
                $this->assertGreaterThanOrEqual(0, $out, "{$class}::compute() must be >= 0");
                $this->assertLessThanOrEqual(0xFFFFFFFF, $out, "{$class}::compute() must be <= 0xFFFFFFFF");
            }
        }
    }

    public function test_deterministic(): void
    {
        foreach ($this->implementations as $class) {
            /** @var Checksum32Interface $algo */
            $algo = new $class;

            $inputs = [
                '',
                'a',
                'foo',
                'foobar',
                "\x00\x01\x02\x03\x04\x05",
                str_repeat('X', 257),
                random_bytes(32),
            ];

            foreach ($inputs as $data) {
                $a = $algo->compute($data);
                $b = $algo->compute($data);
                $this->assertSame($a, $b, "{$class}::compute() must be deterministic");
            }
        }
    }

    public function test_different_inputs_usually_differ(): void
    {
        foreach ($this->implementations as $class) {
            /** @var Checksum32Interface $algo */
            $algo = new $class;

            $this->assertNotSame(
                $algo->compute('foo'),
                $algo->compute('bar'),
                "{$class}::compute() should typically differ for different inputs"
            );

            $this->assertNotSame(
                $algo->compute('foo'),
                $algo->compute('foo '),
                "{$class}::compute() should be sensitive to small changes"
            );
        }
    }

    public function test_large_input_does_not_overflow_or_crash(): void
    {
        foreach ($this->implementations as $class) {
            /** @var Checksum32Interface $algo */
            $algo = new $class;

            $big = random_bytes(1 << 15); // 32 KiB
            $out = $algo->compute($big);

            $this->assertIsInt($out);
            $this->assertGreaterThanOrEqual(0, $out);
            $this->assertLessThanOrEqual(0xFFFFFFFF, $out);
        }
    }
}
