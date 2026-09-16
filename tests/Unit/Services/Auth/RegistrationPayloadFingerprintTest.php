<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\RegistrationPayloadFingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegistrationPayloadFingerprint::class)]
class RegistrationPayloadFingerprintTest extends TestCase
{
    public function testKeyOrderIsCanonicalizedRecursivelyInsideLists(): void
    {
        $fingerprints = new RegistrationPayloadFingerprint();
        self::assertSame(
            $fingerprints->make(['backup' => ['tag' => 'tag', 'iv' => 'iv'], 'keys' => [['key' => 'publicKey', 'value' => ['b' => 2, 'a' => 1]]]]),
            $fingerprints->make(['keys' => [['value' => ['a' => 1, 'b' => 2], 'key' => 'publicKey']], 'backup' => ['iv' => 'iv', 'tag' => 'tag']])
        );
    }

    public function testDifferentValuesAndListOrderRemainDistinct(): void
    {
        $fingerprints = new RegistrationPayloadFingerprint();
        self::assertNotSame($fingerprints->make(['value' => 1]), $fingerprints->make(['value' => 2]));
        self::assertNotSame($fingerprints->make(['value' => 1]), $fingerprints->make(['value' => '1']));
        self::assertNotSame($fingerprints->make(['keys' => ['a', 'b']]), $fingerprints->make(['keys' => ['b', 'a']]));
    }
}
