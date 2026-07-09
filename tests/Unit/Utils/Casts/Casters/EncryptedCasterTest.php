<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters;

use App\Utils\Casts\Casters\EncryptedCaster;
use App\Utils\Casts\Exceptions\InvalidCastTypeException;
use App\Utils\Casts\Values\CastType;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\Casters\EncryptedCasterTestFixtures\EncryptedCasterTestConfig;

#[CoversClass(EncryptedCaster::class)]
#[CoversClass(InvalidCastTypeException::class)]
class EncryptedCasterTest extends TestCase
{
    // ==========================================================================
    // Helpers
    // ==========================================================================

    /**
     * Swap the Crypt facade with a PHPUnit mock expecting exactly one
     * decryptString() call with the given payload.
     */
    private function mockDecryptString(string $payload, string $plaintext): void
    {
        $encrypter = $this->createMock(StringEncrypter::class);
        $encrypter->expects(static::once())
            ->method('decryptString')
            ->with($payload)
            ->willReturn($plaintext);
        Crypt::swap($encrypter);
    }

    /**
     * Swap the Crypt facade with a PHPUnit mock expecting exactly one
     * encryptString() call with the given plaintext.
     */
    private function mockEncryptString(string $plaintext, string $ciphertext): void
    {
        $encrypter = $this->createMock(StringEncrypter::class);
        $encrypter->expects(static::once())
            ->method('encryptString')
            ->with($plaintext)
            ->willReturn($ciphertext);
        Crypt::swap($encrypter);
    }

    // ==========================================================================
    // Hydration — get()
    // ==========================================================================

    public function testItDecryptsString(): void
    {
        $this->mockDecryptString('ciphertext', 'my-secret');

        $sut = new EncryptedCaster(CastType::STRING);
        static::assertSame('my-secret', $sut->get(new \stdClass(), 'ciphertext', 'prop'));
    }

    public function testItDecryptsArray(): void
    {
        $this->mockDecryptString('ciphertext', '["a","b"]');

        $sut = new EncryptedCaster(CastType::ARRAY);
        static::assertSame(['a', 'b'], $sut->get(new \stdClass(), 'ciphertext', 'prop'));
    }

    public function testItDecryptsJsonAliasAsArray(): void
    {
        $this->mockDecryptString('ciphertext', '["x"]');

        $sut = new EncryptedCaster(CastType::JSON);
        static::assertSame(['x'], $sut->get(new \stdClass(), 'ciphertext', 'prop'));
    }

    public function testItDecryptsObject(): void
    {
        $this->mockDecryptString('ciphertext', '{"k":"v"}');

        $sut = new EncryptedCaster(CastType::OBJECT);
        $result = $sut->get(new \stdClass(), 'ciphertext', 'prop');
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertSame('v', $result->k);
    }

    // ==========================================================================
    // Serialization — set()
    // ==========================================================================

    public function testItEncryptsString(): void
    {
        $this->mockEncryptString('my-secret', 'ciphertext');

        $sut = new EncryptedCaster(CastType::STRING);
        static::assertSame('ciphertext', $sut->set(new \stdClass(), 'my-secret', 'prop'));
    }

    public function testItEncryptsArrayAsJson(): void
    {
        $this->mockEncryptString('["a","b"]', 'ciphertext');

        $sut = new EncryptedCaster(CastType::ARRAY);
        $sut->set(new \stdClass(), ['a', 'b'], 'prop');
    }

    public function testItEncryptsObjectAsJson(): void
    {
        $this->mockEncryptString('{"k":"v"}', 'ciphertext');

        $obj = new \stdClass();
        $obj->k = 'v';
        $sut = new EncryptedCaster(CastType::OBJECT);
        $sut->set(new \stdClass(), $obj, 'prop');
    }

    // ==========================================================================
    // argsForAttribute
    // ==========================================================================

    public function testItReturnsArgsForEncryptedColonStringType(): void
    {
        $result = EncryptedCaster::argsForAttribute(null, 'encrypted', 'string');
        static::assertSame([CastType::STRING], $result);
    }

    public function testItReturnsArgsForEncryptedColonArrayType(): void
    {
        $result = EncryptedCaster::argsForAttribute(null, 'encrypted', 'array');
        static::assertSame([CastType::ARRAY], $result);
    }

    public function testItReturnsArgsWhenFormatIsEncryptedKeyword(): void
    {
        // e.g. #[CastedValue(CastType::STRING, 'encrypted')]
        $result = EncryptedCaster::argsForAttribute(CastType::STRING, 'string', 'encrypted');
        static::assertSame([CastType::STRING], $result);
    }

    public function testItReturnsNullForNonEncryptedType(): void
    {
        static::assertNull(EncryptedCaster::argsForAttribute(CastType::INT, 'int', null));
    }

    public function testItReturnsNullWhenTypeAndFormatAreUnrelated(): void
    {
        static::assertNull(EncryptedCaster::argsForAttribute(null, 'SomeClass', null));
    }

    public function testItThrowsForInvalidEncryptedSubType(): void
    {
        $this->expectException(InvalidCastTypeException::class);
        EncryptedCaster::argsForAttribute(null, 'encrypted', 'not_a_valid_type');
    }

    public function testItThrowsForNotEncryptableSubType(): void
    {
        $this->expectException(InvalidCastTypeException::class);
        EncryptedCaster::argsForAttribute(null, 'encrypted', 'int');
    }

    // ==========================================================================
    // argsForProperty
    // ==========================================================================

    public function testItAlwaysReturnsNullForProperty(): void
    {
        // Encrypted inner type cannot be inferred from the PHP type hint alone.
        $prop = new \ReflectionProperty(EncryptedCasterTestConfig::class, 'stringProp');
        static::assertNull(EncryptedCaster::argsForProperty($prop));
    }
}
