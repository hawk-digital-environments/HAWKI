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
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsEnumConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsInvalidClassConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsInvalidUnionConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsMixedConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsNestedInnerConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsNestedOuterConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsNullableConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsSimpleTypesConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsStaticPropertyConfig;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsTestDirection;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsTestPriority;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsTestStatus;

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

        self::assertInstanceOf(CastsSimpleTypesConfig::class, $sut);
    }

    // ==========================================================================
    // fromStringArray with typed (non-string) values — the cast juggling
    // ==========================================================================

    public function testItHydratesNonStringValuesThroughThePropertyCast(): void
    {
        // Typed values (e.g. validated request data) are juggled through the property's
        // cast — serialize via set(), hydrate via get() — producing the same result
        // as the equivalent stored strings.
        $fromTyped = CastsSimpleTypesConfig::fromStringArray([
            'count' => 42,
            'price' => 9.99,
            'active' => true,
            'tags' => ['a', 'b'],
        ]);
        $fromStored = CastsSimpleTypesConfig::fromStringArray([
            'count' => '42',
            'price' => '9.99',
            'active' => '1',
            'tags' => '["a","b"]',
        ]);

        self::assertSame($fromStored->count, $fromTyped->count);
        self::assertSame($fromStored->price, $fromTyped->price);
        self::assertSame($fromStored->active, $fromTyped->active);
        self::assertSame($fromStored->tags, $fromTyped->tags);
    }

    public function testItHydratesEnumInstancesAsNonStringValues(): void
    {
        $sut = CastsEnumConfig::fromStringArray([
            'status' => CastsTestStatus::Inactive,
            'direction' => CastsTestDirection::South,
            'priority' => CastsTestPriority::High,
        ]);

        self::assertSame(CastsTestStatus::Inactive, $sut->status);
        self::assertSame(CastsTestDirection::South, $sut->direction);
        self::assertSame(CastsTestPriority::High, $sut->priority);
    }

    public function testItHydratesIntBackedEnumsFromStoredStrings(): void
    {
        // The stored value is always a string — the EnumCaster must coerce it back
        // to the enum's backing type.
        $sut = CastsEnumConfig::fromStringArray(['priority' => '2']);

        self::assertSame(CastsTestPriority::High, $sut->priority);
    }

    public function testItCreatesFromArray(): void
    {
        $sut = CastsSimpleTypesConfig::fromArray([
            'count' => 42,
            'active' => true,
        ]);

        self::assertInstanceOf(CastsSimpleTypesConfig::class, $sut);
        self::assertSame(42, $sut->count);
        self::assertTrue($sut->active);
    }

    public function testItRetainsDefaultsForMissingKeys(): void
    {
        $sut = CastsSimpleTypesConfig::fromStringArray([]);

        self::assertSame(0, $sut->count);
        self::assertSame(0.0, $sut->price);
        self::assertFalse($sut->active);
        self::assertSame('', $sut->name);
        self::assertSame([], $sut->tags);
    }

    public function testItIgnoresNullValues(): void
    {
        $sut = CastsSimpleTypesConfig::fromStringArray(['count' => null]);

        self::assertSame(0, $sut->count);
    }

    // ==========================================================================
    // Raw string passthrough (no type hint)
    // ==========================================================================

    public function testItPassesThroughRawStringForUntypedProperty(): void
    {
        $sut = CastsMixedConfig::fromStringArray(['raw' => 'raw-value']);
        self::assertSame('raw-value', $sut->raw);
    }

    public function testItSerializesUntypedPropertyAsStringInToArrayList(): void
    {
        $sut = CastsMixedConfig::fromArray(['raw' => 'hello']);
        self::assertSame('hello', $sut->toStringArray()['raw']);
    }

    // ==========================================================================
    // toArrayList — serialization
    // ==========================================================================

    public function testItSerializesNullPropertyAsNull(): void
    {
        $sut = CastsNullableConfig::fromStringArray(['value' => null]);
        self::assertNull($sut->toStringArray()['value']);
    }

    // ==========================================================================
    // Custom casters
    // ==========================================================================

    public function testItUsesCustomCasterOnHydrate(): void
    {
        $sut = CastsCustomCasterConfig::fromStringArray(['value' => 'raw']);
        self::assertSame('custom:raw', $sut->value);
    }

    public function testItUsesCustomCasterOnSerialize(): void
    {
        $sut = CastsCustomCasterConfig::fromArray(['value' => 'custom:raw']);
        self::assertSame('raw', $sut->toStringArray()['value']);
    }

    public function testItPassesParentObjectToCustomCaster(): void
    {
        $sut = CastsContextAwareCasterConfig::fromStringArray(['locale' => 'de', 'label' => 'Hallo']);
        self::assertSame('de:Hallo', $sut->label);
    }

    public function testItUsesCustomCasterWithConstructorArgsOnHydrate(): void
    {
        $sut = CastsCustomCasterWithArgsConfig::fromStringArray(['value' => 'raw']);
        self::assertSame('prefixed:raw', $sut->value);
    }

    public function testItUsesCustomCasterWithConstructorArgsOnSerialize(): void
    {
        $sut = CastsCustomCasterWithArgsConfig::fromArray(['value' => 'prefixed:raw']);
        self::assertSame('raw', $sut->toStringArray()['value']);
    }

    // ==========================================================================
    // getCasts
    // ==========================================================================

    public function testItCachesCastMap(): void
    {
        $sut1 = CastsSimpleTypesConfig::fromStringArray([]);
        $sut2 = CastsSimpleTypesConfig::fromStringArray([]);

        self::assertSame($sut1->getCasts(), $sut2->getCasts());
    }

    public function testItIgnoresStaticPropertiesInToArrayList(): void
    {
        $sut = CastsStaticPropertyConfig::fromStringArray(['name' => 'test']);
        $result = $sut->toStringArray();

        self::assertArrayHasKey('name', $result);
        self::assertArrayNotHasKey('ignored', $result);
    }

    // ==========================================================================
    // Exception cases
    // ==========================================================================

    public function testItThrowsForUnionType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf(
            '%s::$%s has a union/intersection type and requires an explicit #[CastedValue] annotation.',
            CastsInvalidUnionConfig::class,
            'value',
        ));

        CastsInvalidUnionConfig::fromStringArray(['value' => '1']);
    }

    public function testItThrowsForNonBuiltinClassType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf(
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

        self::assertInstanceOf(CastsNestedOuterConfig::class, $sut);
        self::assertSame('outer', $sut->tag);
        self::assertInstanceOf(CastsNestedInnerConfig::class, $sut->inner);
        self::assertSame('hello', $sut->inner->value);
        self::assertSame(5, $sut->inner->num);
    }

    public function testItSerializesNestedCastableObjectToSingleJsonObject(): void
    {
        $inner = CastsNestedInnerConfig::fromArray(['value' => 'hello', 'num' => 5]);
        $sut = CastsNestedOuterConfig::fromArray(['tag' => 'outer', 'inner' => $inner]);

        $result = $sut->toStringArray();

        self::assertSame('outer', $result['tag']);
        self::assertSame('{"value":"hello","num":"5"}', $result['inner']);
        // Must be valid JSON — not an escaped string within a string
        $decoded = json_decode((string) $result['inner'], true);
        self::assertIsArray($decoded);
        self::assertSame('hello', $decoded['value']);
    }

    public function testItInfersNestedCastableObjectCastWithoutAnnotation(): void
    {
        $casts = CastsNestedOuterConfig::fromStringArray([])->getCasts();

        self::assertArrayHasKey('inner', $casts);
    }

    // ==========================================================================
    // Encrypted — end-to-end pipeline
    // ==========================================================================

    public function testItDecryptsOnHydrateViaFullPipeline(): void
    {
        $encrypter = $this->createMock(StringEncrypter::class);
        $encrypter->expects($this->once())
            ->method('decryptString')
            ->with('ciphertext')
            ->willReturn('my-secret');
        Crypt::swap($encrypter);

        $sut = CastsEncryptedStringConfig::fromStringArray(['secret' => 'ciphertext']);
        self::assertSame('my-secret', $sut->secret);
    }

    public function testItEncryptsOnSerializeViaFullPipeline(): void
    {
        $encrypter = $this->createMock(StringEncrypter::class);
        $encrypter->expects($this->once())
            ->method('encryptString')
            ->with('my-secret')
            ->willReturn('ciphertext');
        Crypt::swap($encrypter);

        $sut = CastsEncryptedStringConfig::fromArray(['secret' => 'my-secret']);
        self::assertSame('ciphertext', $sut->toStringArray()['secret']);
    }
}
