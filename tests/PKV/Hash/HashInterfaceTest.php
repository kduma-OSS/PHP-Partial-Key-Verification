<?php

declare(strict_types=1);

namespace Tests\PKV\Hash;

use KDuma\PKV\Hash\HashInterface;
use PHPUnit\Framework\TestCase;

class HashInterfaceTest extends TestCase
{
    /**
     * @var class-string<HashInterface>[]
     */
    private array $implementations = [
        \KDuma\PKV\Hash\Crc32::class,
        \KDuma\PKV\Hash\Fnv1a::class,
        \KDuma\PKV\Hash\GeneralizedCrc::class,
        // \KDuma\PKV\Hash\Jenkins06::class,
        \KDuma\PKV\Hash\Jenkins96::class,
        \KDuma\PKV\Hash\OneAtATime::class,
        \KDuma\PKV\Hash\SuperFast::class,
    ];

    public function test_compute_returns_unsigned32bit_int(): void
    {
        foreach ($this->implementations as $class) {
            $hash = new $class;

            $out = $hash->compute('foobar');

            $this->assertIsInt($out, "{$class}::compute() must return int");
            $this->assertGreaterThanOrEqual(0, $out, "{$class}::compute() must be >= 0");
            $this->assertLessThanOrEqual(0xFFFFFFFF, $out, "{$class}::compute() must be <= 0xFFFFFFFF");
        }
    }

    public function test_compute_is_deterministic(): void
    {
        foreach ($this->implementations as $class) {
            $hash = new $class;

            $out1 = $hash->compute('foobar');
            $out2 = $hash->compute('foobar');

            $this->assertSame($out1, $out2, "{$class}::compute() must be deterministic");
        }
    }

    public function test_different_inputs_give_different_hashes(): void
    {
        foreach ($this->implementations as $class) {
            $hash = new $class;

            $a = $hash->compute('foo');
            $b = $hash->compute('bar');

            $this->assertNotSame($a, $b, "{$class}::compute() should differ for different inputs");
        }
    }
}
