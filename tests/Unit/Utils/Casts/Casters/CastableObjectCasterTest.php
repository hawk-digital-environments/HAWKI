<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters;

use App\Utils\Casts\Casters\CastableObjectCaster;
use App\Utils\Casts\Values\CastType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures\DeepConfig;
use Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures\InnerConfig;
use Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures\OuterConfig;
use Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures\PropertyInspectionFixture;

#[CoversClass(CastableObjectCaster::class)]
class CastableObjectCasterTest extends TestCase
{
    protected function setUp(): void
    {
        InnerConfig::reset();
        OuterConfig::reset();
        DeepConfig::reset();
    }

    // =========================================================================
    // get() — hydration
    // =========================================================================

    public function testItHydratesFromFlatJson(): void
    {
        $sut = new CastableObjectCaster(InnerConfig::class);

        $result = $sut->get(new \stdClass(), '{"label":"hello","count":"7"}', 'prop');

        static::assertInstanceOf(InnerConfig::class, $result);
        static::assertSame('hello', $result->label);
        static::assertSame(7, $result->count);
    }

    public function testItReturnsEmptyInstanceForInvalidJson(): void
    {
        $sut = new CastableObjectCaster(InnerConfig::class);

        $result = $sut->get(new \stdClass(), 'not-json', 'prop');

        static::assertInstanceOf(InnerConfig::class, $result);
        static::assertSame('', $result->label);
        static::assertSame(0, $result->count);
    }

    public function testItHydratesNestedCastableObject(): void
    {
        $sut = new CastableObjectCaster(OuterConfig::class);

        // Inner object is stored as an escaped JSON string within the outer JSON object.
        $json = (string)json_encode(['name' => 'outer', 'inner' => json_encode(['label' => 'inner', 'count' => '3'])]);
        $result = $sut->get(new \stdClass(), $json, 'prop');

        static::assertInstanceOf(OuterConfig::class, $result);
        static::assertSame('outer', $result->name);
        static::assertInstanceOf(InnerConfig::class, $result->inner);
        static::assertSame('inner', $result->inner->label);
        static::assertSame(3, $result->inner->count);
    }

    public function testItHydratesThreeLevelsDeep(): void
    {
        $sut = new CastableObjectCaster(DeepConfig::class);

        // Each nesting level is an escaped JSON string inside its parent.
        $innerJson = (string)json_encode(['label' => 'leaf', 'count' => '9']);
        $outerJson = (string)json_encode(['name' => 'mid', 'inner' => $innerJson]);
        $deepJson  = (string)json_encode(['title' => 'deep', 'outer' => $outerJson]);

        $result = $sut->get(new \stdClass(), $deepJson, 'prop');

        static::assertInstanceOf(DeepConfig::class, $result);
        static::assertSame('deep', $result->title);
        static::assertSame('mid', $result->outer->name);
        static::assertSame('leaf', $result->outer->inner->label);
        static::assertSame(9, $result->outer->inner->count);
    }

    public function testItPreservesNullValuesOnHydration(): void
    {
        $sut = new CastableObjectCaster(InnerConfig::class);

        $result = $sut->get(new \stdClass(), '{"label":null,"count":"1"}', 'prop');

        static::assertSame('', $result->label);
        static::assertSame(1, $result->count);
    }

    // =========================================================================
    // set() — serialization
    // =========================================================================

    public function testItSerializesToFlatJson(): void
    {
        $sut = new CastableObjectCaster(InnerConfig::class);
        $inner = InnerConfig::fromArray(['label' => 'hello', 'count' => 7]);

        $result = $sut->set(new \stdClass(), $inner, 'prop');

        static::assertSame('{"label":"hello","count":"7"}', $result);
    }

    public function testItSerializesNestedCastableObjectAsEscapedJsonString(): void
    {
        $sut = new CastableObjectCaster(OuterConfig::class);
        $outer = OuterConfig::fromArray([
            'name' => 'outer',
            'inner' => InnerConfig::fromArray(['label' => 'inner', 'count' => 3]),
        ]);

        $result = $sut->set(new \stdClass(), $outer, 'prop');

        // The inner object is stored as an escaped JSON string within the outer JSON object.
        $expected = (string)json_encode(['name' => 'outer', 'inner' => json_encode(['label' => 'inner', 'count' => '3'])]);
        static::assertSame($expected, $result);
    }

    public function testItSerializesThreeLevelsDeepAsNestedEscapedJsonStrings(): void
    {
        $sut = new CastableObjectCaster(DeepConfig::class);
        $deep = DeepConfig::fromArray([
            'title' => 'deep',
            'outer' => OuterConfig::fromArray([
                'name' => 'mid',
                'inner' => InnerConfig::fromArray(['label' => 'leaf', 'count' => 9]),
            ]),
        ]);

        $result = $sut->set(new \stdClass(), $deep, 'prop');

        // Each nesting level is an escaped JSON string inside its parent.
        $innerJson = (string)json_encode(['label' => 'leaf', 'count' => '9']);
        $outerJson = (string)json_encode(['name' => 'mid', 'inner' => $innerJson]);
        $expected  = (string)json_encode(['title' => 'deep', 'outer' => $outerJson]);
        static::assertSame($expected, $result);
        static::assertIsString(json_decode($result, true)['outer']);
    }

    public function testItReturnsEmptyJsonObjectForNonCastableValue(): void
    {
        $sut = new CastableObjectCaster(InnerConfig::class);

        $result = $sut->set(new \stdClass(), 'not-a-castable-object', 'prop');

        static::assertSame('{}', $result);
    }

    // =========================================================================
    // round-trip
    // =========================================================================

    public function testItRoundTripsNestedObject(): void
    {
        $sut = new CastableObjectCaster(OuterConfig::class);
        $original = OuterConfig::fromArray([
            'name' => 'test',
            'inner' => InnerConfig::fromArray(['label' => 'abc', 'count' => 42]),
        ]);

        $serialized = $sut->set(new \stdClass(), $original, 'prop');
        $hydrated = $sut->get(new \stdClass(), $serialized, 'prop');

        static::assertInstanceOf(OuterConfig::class, $hydrated);
        static::assertSame('test', $hydrated->name);
        static::assertSame('abc', $hydrated->inner->label);
        static::assertSame(42, $hydrated->inner->count);
    }

    // =========================================================================
    // argsForAttribute
    // =========================================================================

    public function testItReturnsArgsForCastableObjectTypeString(): void
    {
        $result = CastableObjectCaster::argsForAttribute(null, InnerConfig::class, null);

        static::assertSame([InnerConfig::class], $result);
    }

    public function testItReturnsNullWhenCastTypeIsResolved(): void
    {
        static::assertNull(CastableObjectCaster::argsForAttribute(CastType::INT, 'int', null));
    }

    public function testItReturnsNullForNonCastableClassString(): void
    {
        static::assertNull(CastableObjectCaster::argsForAttribute(null, \stdClass::class, null));
    }

    public function testItReturnsNullForUnknownString(): void
    {
        static::assertNull(CastableObjectCaster::argsForAttribute(null, 'NotAClass', null));
    }

    // =========================================================================
    // argsForProperty
    // =========================================================================

    public function testItReturnsArgsForCastableObjectProperty(): void
    {
        $prop = new \ReflectionProperty(PropertyInspectionFixture::class, 'castableProp');

        static::assertSame([InnerConfig::class], CastableObjectCaster::argsForProperty($prop));
    }

    public function testItReturnsNullForBuiltinTypeProperty(): void
    {
        $prop = new \ReflectionProperty(PropertyInspectionFixture::class, 'notCastableProp');

        static::assertNull(CastableObjectCaster::argsForProperty($prop));
    }

    public function testItReturnsNullForUntypedProperty(): void
    {
        $prop = new \ReflectionProperty(PropertyInspectionFixture::class, 'untypedProp');

        static::assertNull(CastableObjectCaster::argsForProperty($prop));
    }
}
