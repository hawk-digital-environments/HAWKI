<?php

declare(strict_types=1);

namespace Tests\Unit\Casts;

use App\Casts\AsHybridCryptoValueCast;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AsHybridCryptoValueCast::class)]
class AsHybridCryptoValueCastTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new AsHybridCryptoValueCast();
        static::assertInstanceOf(AsHybridCryptoValueCast::class, $sut);
    }

    // =========================================================================
    // get()
    // =========================================================================

    public function testItGetCastsStringToHybridCryptoValue(): void
    {
        $symmetricValue = new SymmetricCryptoValue('iv', 'tag', 'ciphertext');
        $expected = new HybridCryptoValue('passphrase', $symmetricValue);
        $serialized = (string) $expected;

        $sut = new AsHybridCryptoValueCast();
        $model = $this->createMock(Model::class);

        $result = $sut->get($model, 'key', $serialized, []);

        static::assertInstanceOf(HybridCryptoValue::class, $result);
        static::assertSame('passphrase', $result->passphrase);
        static::assertSame('iv', $result->value->iv);
        static::assertSame('tag', $result->value->tag);
        static::assertSame('ciphertext', $result->value->ciphertext);
    }

    // =========================================================================
    // set()
    // =========================================================================

    public function testItSetCastsHybridCryptoValueToString(): void
    {
        $symmetricValue = new SymmetricCryptoValue('iv', 'tag', 'ciphertext');
        $value = new HybridCryptoValue('passphrase', $symmetricValue);

        $sut = new AsHybridCryptoValueCast();
        $model = $this->createMock(Model::class);

        $result = $sut->set($model, 'key', $value, []);

        static::assertSame((string) $value, $result);
    }

    public function testItSetPassesThroughStringValue(): void
    {
        $sut = new AsHybridCryptoValueCast();
        $model = $this->createMock(Model::class);

        $result = $sut->set($model, 'key', 'raw-string-value', []);

        static::assertSame('raw-string-value', $result);
    }
}
