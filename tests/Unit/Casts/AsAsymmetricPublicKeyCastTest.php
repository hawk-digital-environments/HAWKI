<?php

declare(strict_types=1);

namespace Tests\Unit\Casts;

use App\Casts\AsAsymmetricPublicKey;
use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AsAsymmetricPublicKey::class)]
class AsAsymmetricPublicKeyCastTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new AsAsymmetricPublicKey();
        static::assertInstanceOf(AsAsymmetricPublicKey::class, $sut);
    }

    // =========================================================================
    // get()
    // =========================================================================

    public function testItGetCastsStringToAsymmetricPublicKey(): void
    {
        $expected = new AsymmetricPublicKey('serverKey', 'webKey');
        $serialized = (string)$expected;

        $sut = new AsAsymmetricPublicKey();
        $model = $this->createMock(Model::class);

        $result = $sut->get($model, 'key', $serialized, []);

        static::assertInstanceOf(AsymmetricPublicKey::class, $result);
        static::assertSame('serverKey', $result->server);
        static::assertSame('webKey', $result->web);
    }

    // =========================================================================
    // set()
    // =========================================================================

    public function testItSetCastsAsymmetricPublicKeyToString(): void
    {
        $value = new AsymmetricPublicKey('serverKey', 'webKey');

        $sut = new AsAsymmetricPublicKey();
        $model = $this->createMock(Model::class);

        $result = $sut->set($model, 'key', $value, []);

        static::assertSame((string)$value, $result);
    }

    public function testItSetPassesThroughStringValue(): void
    {
        $sut = new AsAsymmetricPublicKey();
        $model = $this->createMock(Model::class);

        $result = $sut->set($model, 'key', 'raw-string-value', []);

        static::assertSame('raw-string-value', $result);
    }
}
