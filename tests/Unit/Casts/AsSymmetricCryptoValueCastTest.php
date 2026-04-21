<?php

declare(strict_types=1);

namespace Tests\Unit\Casts;

use App\Casts\AsSymmetricCryptoValueCast;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AsSymmetricCryptoValueCast::class)]
class AsSymmetricCryptoValueCastTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new AsSymmetricCryptoValueCast();
        static::assertInstanceOf(AsSymmetricCryptoValueCast::class, $sut);
    }

    // =========================================================================
    // get()
    // =========================================================================

    public function testItGetCastsStringToSymmetricCryptoValue(): void
    {
        $expected = new SymmetricCryptoValue('iv', 'tag', 'ciphertext');
        $serialized = (string) $expected;

        $sut = new AsSymmetricCryptoValueCast();
        $model = $this->createMock(Model::class);

        $result = $sut->get($model, 'key', $serialized, []);

        static::assertInstanceOf(SymmetricCryptoValue::class, $result);
        static::assertSame('iv', $result->iv);
        static::assertSame('tag', $result->tag);
        static::assertSame('ciphertext', $result->ciphertext);
    }

    // =========================================================================
    // set()
    // =========================================================================

    public function testItSetCastsSymmetricCryptoValueToString(): void
    {
        $value = new SymmetricCryptoValue('iv', 'tag', 'ciphertext');

        $sut = new AsSymmetricCryptoValueCast();
        $model = $this->createMock(Model::class);

        $result = $sut->set($model, 'key', $value, []);

        static::assertSame((string) $value, $result);
    }

    public function testItSetPassesThroughStringValue(): void
    {
        $sut = new AsSymmetricCryptoValueCast();
        $model = $this->createMock(Model::class);

        $result = $sut->set($model, 'key', 'raw-string-value', []);

        static::assertSame('raw-string-value', $result);
    }
}
