<?php

declare(strict_types=1);

namespace Tests\PKV;

use KDuma\PKV\Checksum\Adler16;
use KDuma\PKV\Hash\Fnv1a;
use KDuma\PKV\Hash\OneAtATime;
use KDuma\PKV\Hash\SuperFast;
use KDuma\PKV\PartialKeyValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\KDuma\PKV\PartialKeyValidator::class)] final class CrossPlatformPartialKeyValidatorTest extends TestCase
{
    /**
     * Port of the helper from C# test class.
     */
    private function validateKey(string $userName, string $key): bool
    {
        $seed = PartialKeyValidator::getSerialNumberFromKey($key);
        $blacklist = [1518008798];

        if (\in_array($seed, $blacklist, true)) {
            return false;
        }

        $validator = new PartialKeyValidator(new Fnv1a);

        // Validation for key with index 1
        if (! $validator->validateKeyWithSeedString(
            new Adler16,
            new OneAtATime,
            $key,
            1,
            766109221,
            $userName
        )) {
            return false;
        }

        // Validation for key with index 4
        if (! $validator->validateKeyWithSeedString(
            new Adler16,
            new SuperFast,
            $key,
            4,
            4072442218,
            $userName
        )) {
            return false;
        }

        return true;
    }

    public function test_correct_key(): void
    {
        $key = 'HL65W5-KK6Y34-OBG32G-DM522M-H2ZI2E-4366ZG-UP57MM';
        $serial = 1977351482;
        $name = 'Correct Key';

        $this->assertTrue($this->validateKey($name, $key));
        $this->assertSame($serial, PartialKeyValidator::getSerialNumberFromKey($key));
    }

    public function test_correct_key_but_incorrect_name(): void
    {
        $key = 'LO3PLL-FWQ3MQ-JPC4OI-4XUGGM-Z6EVVP-DTGWJ2-MZW6BE';
        $serial = 2901784155;
        $name = 'Correct Key But Incorrect Name';

        $this->assertFalse($this->validateKey($name, $key));
        $this->assertSame($serial, PartialKeyValidator::getSerialNumberFromKey($key));
    }

    public function test_blacklisted_key(): void
    {
        $key = '334XUW-WDB6RD-MLHYSP-CLJU7H-66DPW5-G3CZK3-P2S5LM';
        $serial = 1518008798;
        $name = 'Blacklisted Key';

        $this->assertFalse($this->validateKey($name, $key));
        $this->assertSame($serial, PartialKeyValidator::getSerialNumberFromKey($key));
    }
}
