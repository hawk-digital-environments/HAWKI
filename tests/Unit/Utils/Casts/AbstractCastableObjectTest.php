<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts;

use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\CastedValue;
use App\Utils\Casts\Exceptions\InvalidCastTypeException;
use App\Utils\Casts\Values\CastType;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsContextAwareCasterConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsCustomCasterConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsCustomCasterWithArgsConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsEncryptedStringConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsInvalidClassConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsInvalidUnionConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsMixedConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsNestedInnerConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsNestedOuterConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsNullableConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsSimpleTypesConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsStaticPropertyConfig;

#[CoversClass(AbstractCastableObject::class)]
#[CoversClass(CastType::class)]
#[CoversClass(CastedValue::class)]
#[CoversClass(InvalidCastTypeException::class)]
class AbstractCastableObjectTest extends TestCase
{
    protected function setUp(): void
    {
        CastsSimpleTypesConfig::reset();
        CastsContextAwareCasterConfig::reset();
        CastsCustomCasterConfig::reset();
        CastsCustomCasterWithArgsConfig::reset();
        CastsEncryptedStringConfig::reset();
        CastsInvalidClassConfig::reset();
        CastsInvalidUnionConfig::reset();
        CastsMixedConfig::reset();
        CastsNullableConfig::reset();
        CastsSimpleTypesConfig::reset();
        CastsStaticPropertyConfig::reset();
        CastsNestedInnerConfig::reset();
        CastsNestedOuterConfig::reset();
    }

    // ==========================================================================
    // fromStringArray / fromArray construction
    // ==========================================================================

    public function testItCreatesFromStringArray(): void
    {
        $sut = CastsSimpleTypesConfig::fromStringArray([
            'count' => '42',
            'price' => '9.99',
            'active' => '1',
            'name' => 'hello',
            'tags' => '["a","b"]',
        ]);

        static::assertInstanceOf(CastsSimpleTypesConfig::class, $sut);
    }

    public function testItCreatesFromArray(): void
    {
        $sut = CastsSimpleTypesConfig::fromArray([
            'count' => 42,
            'active' => true,
        ]);

        static::assertInstanceOf(CastsSimpleTypesConfig::class, $sut);
        static::assertSame(42, $sut->count);
        static::assertTrue($sut->active);
    }

    public function testItRetainsDefaultsForMissingKeys(): void
    {
        $sut = CastsSimpleTypesConfig::fromStringArray([]);

        static::assertSame(0, $sut->count);
        static::assertSame(0.0, $sut->price);
        static::assertFalse($sut->active);
        static::assertSame('', $sut->name);
        static::assertSame([], $sut->tags);
    }

    public function testItIgnoresNullValues(): void
    {
        $sut = CastsSimpleTypesConfig::fromStringArray(['count' => null]);

        static::assertSame(0, $sut->count);
    }

    // ==========================================================================
    // Raw string passthrough (no type hint)
    // ==========================================================================

    public function testItPassesThroughRawStringForUntypedProperty(): void
    {
        $sut = CastsMixedConfig::fromStringArray(['raw' => 'raw-value']);
        static::assertSame('raw-value', $sut->raw);
    }

    public function testItSerializesUntypedPropertyAsStringInToArrayList(): void
    {
        $sut = CastsMixedConfig::fromArray(['raw' => 'hello']);
        static::assertSame('hello', $sut->toStringArray()['raw']);
    }

    // ==========================================================================
    // toArrayList — serialization
    // ==========================================================================

    public function testItSerializesNullPropertyAsNull(): void
    {
        $sut = CastsNullableConfig::fromStringArray(['value' => null]);
        static::assertNull($sut->toStringArray()['value']);
    }

    // ==========================================================================
    // Custom casters
    // ==========================================================================

    public function testItUsesCustomCasterOnHydrate(): void
    {
        $sut = CastsCustomCasterConfig::fromStringArray(['value' => 'raw']);
        static::assertSame('custom:raw', $sut->value);
    }

    public function testItUsesCustomCasterOnSerialize(): void
    {
        $sut = CastsCustomCasterConfig::fromArray(['value' => 'custom:raw']);
        static::assertSame('raw', $sut->toStringArray()['value']);
    }

    public function testItPassesParentObjectToCustomCaster(): void
    {
        $sut = CastsContextAwareCasterConfig::fromStringArray(['locale' => 'de', 'label' => 'Hallo']);
        static::assertSame('de:Hallo', $sut->label);
    }

    public function testItUsesCustomCasterWithConstructorArgsOnHydrate(): void
    {
        $sut = CastsCustomCasterWithArgsConfig::fromStringArray(['value' => 'raw']);
        static::assertSame('prefixed:raw', $sut->value);
    }

    public function testItUsesCustomCasterWithConstructorArgsOnSerialize(): void
    {
        $sut = CastsCustomCasterWithArgsConfig::fromArray(['value' => 'prefixed:raw']);
        static::assertSame('raw', $sut->toStringArray()['value']);
    }

    // ==========================================================================
    // getCasts
    // ==========================================================================

    public function testItCachesCastMap(): void
    {
        $sut1 = CastsSimpleTypesConfig::fromStringArray([]);
        $sut2 = CastsSimpleTypesConfig::fromStringArray([]);

        static::assertSame($sut1->getCasts(), $sut2->getCasts());
    }

    public function testItIgnoresStaticPropertiesInToArrayList(): void
    {
        $sut = CastsStaticPropertyConfig::fromStringArray(['name' => 'test']);
        $result = $sut->toStringArray();

        static::assertArrayHasKey('name', $result);
        static::assertArrayNotHasKey('ignored', $result);
    }

    // ==========================================================================
    // Exception cases
    // ==========================================================================

    public function testItThrowsForUnionType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s::$%s has a union/intersection type and requires an explicit #[CastedValue] annotation.',
            CastsInvalidUnionConfig::class,
            'value',
        ));

        CastsInvalidUnionConfig::fromStringArray(['value' => '1']);
    }

    public function testItThrowsForNonBuiltinClassType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s::$%s has type "%s" which cannot be cast automatically. Add a #[CastedValue] annotation.',
            CastsInvalidClassConfig::class,
            'value',
            \stdClass::class,
        ));

        CastsInvalidClassConfig::fromStringArray(['value' => '{}']);
    }

    // ==========================================================================
    // Nested castable objects — end-to-end pipeline
    // ==========================================================================

    public function testItHydratesNestedCastableObjectFromJsonString(): void
    {
        $sut = CastsNestedOuterConfig::fromStringArray([
            'tag' => 'outer',
            'inner' => '{"value":"hello","num":"5"}',
        ]);

        static::assertInstanceOf(CastsNestedOuterConfig::class, $sut);
        static::assertSame('outer', $sut->tag);
        static::assertInstanceOf(CastsNestedInnerConfig::class, $sut->inner);
        static::assertSame('hello', $sut->inner->value);
        static::assertSame(5, $sut->inner->num);
    }

    public function testItSerializesNestedCastableObjectToSingleJsonObject(): void
    {
        $inner = CastsNestedInnerConfig::fromArray(['value' => 'hello', 'num' => 5]);
        $sut = CastsNestedOuterConfig::fromArray(['tag' => 'outer', 'inner' => $inner]);

        $result = $sut->toStringArray();

        static::assertSame('outer', $result['tag']);
        static::assertSame('{"value":"hello","num":"5"}', $result['inner']);
        // Must be valid JSON — not an escaped string within a string
        $decoded = json_decode((string)$result['inner'], true);
        static::assertIsArray($decoded);
        static::assertSame('hello', $decoded['value']);
    }

    public function testItInfersNestedCastableObjectCastWithoutAnnotation(): void
    {
        $casts = CastsNestedOuterConfig::fromStringArray([])->getCasts();

        static::assertArrayHasKey('inner', $casts);
    }

    // ==========================================================================
    // Encrypted — end-to-end pipeline
    // ==========================================================================

    public function testItDecryptsOnHydrateViaFullPipeline(): void
    {
        $encrypter = $this->createMock(StringEncrypter::class);
        $encrypter->expects(static::once())
            ->method('decryptString')
            ->with('ciphertext')
            ->willReturn('my-secret');
        Crypt::swap($encrypter);

        $sut = CastsEncryptedStringConfig::fromStringArray(['secret' => 'ciphertext']);
        static::assertSame('my-secret', $sut->secret);
    }

    public function testItEncryptsOnSerializeViaFullPipeline(): void
    {
        $encrypter = $this->createMock(StringEncrypter::class);
        $encrypter->expects(static::once())
            ->method('encryptString')
            ->with('my-secret')
            ->willReturn('ciphertext');
        Crypt::swap($encrypter);

        $sut = CastsEncryptedStringConfig::fromArray(['secret' => 'my-secret']);
        static::assertSame('ciphertext', $sut->toStringArray()['secret']);
    }
}
