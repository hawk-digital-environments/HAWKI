<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters;

use App\Utils\Casts\Casters\DefaultCaster;
use App\Utils\Casts\Values\CastType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\Casters\DefaultCasterTestFixtures\DefaultCasterTestConfig;

#[CoversClass(DefaultCaster::class)]
class DefaultCasterTest extends TestCase
{
    // ==========================================================================
    // Hydration — get()
    // ==========================================================================

    public function testItHydratesInt(): void
    {
        $sut = new DefaultCaster(CastType::INT);
        static::assertSame(42, $sut->get(new \stdClass(), '42'));
    }

    public function testItHydratesFloat(): void
    {
        $sut = new DefaultCaster(CastType::FLOAT);
        static::assertSame(3.14, $sut->get(new \stdClass(), '3.14'));
    }

    #[DataProvider('provideTestItHydratesBoolData')]
    public function testItHydratesBool(string $stored, bool $expected): void
    {
        $sut = new DefaultCaster(CastType::BOOL);
        static::assertSame($expected, $sut->get(new \stdClass(), $stored));
    }

    public static function provideTestItHydratesBoolData(): iterable
    {
        yield 'truthy 1' => ['1', true];
        yield 'truthy true' => ['true', true];
        yield 'falsy 0' => ['0', false];
        yield 'falsy empty' => ['', false];
        yield 'falsy arbitrary string' => ['yes', false];
    }

    public function testItHydratesString(): void
    {
        $sut = new DefaultCaster(CastType::STRING);
        static::assertSame('hello world', $sut->get(new \stdClass(), 'hello world'));
    }

    public function testItHydratesArray(): void
    {
        $sut = new DefaultCaster(CastType::ARRAY);
        static::assertSame(['x', 'y'], $sut->get(new \stdClass(), '["x","y"]'));
    }

    public function testItHydratesArrayFallsBackToEmptyOnInvalidJson(): void
    {
        $sut = new DefaultCaster(CastType::ARRAY);
        static::assertSame([], $sut->get(new \stdClass(), 'not-json'));
    }

    public function testItHydratesJsonAliasAsArray(): void
    {
        $sut = new DefaultCaster(CastType::JSON);
        static::assertSame(['a'], $sut->get(new \stdClass(), '["a"]'));
    }

    public function testItHydratesObject(): void
    {
        $sut = new DefaultCaster(CastType::OBJECT);
        $result = $sut->get(new \stdClass(), '{"key":"val"}');
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertSame('val', $result->key);
    }

    public function testItHydratesObjectFallsBackToEmptyStdClassOnInvalidJson(): void
    {
        $sut = new DefaultCaster(CastType::OBJECT);
        static::assertInstanceOf(\stdClass::class, $sut->get(new \stdClass(), 'not-json'));
    }

    // ==========================================================================
    // Serialization — set()
    // ==========================================================================

    public function testItSerializesInt(): void
    {
        $sut = new DefaultCaster(CastType::INT);
        static::assertSame('42', $sut->set(new \stdClass(), 42));
    }

    public function testItSerializesFloat(): void
    {
        $sut = new DefaultCaster(CastType::FLOAT);
        static::assertSame('3.14', $sut->set(new \stdClass(), 3.14));
    }

    public function testItSerializesBoolTrueAsOne(): void
    {
        $sut = new DefaultCaster(CastType::BOOL);
        static::assertSame('1', $sut->set(new \stdClass(), true));
    }

    public function testItSerializesBoolFalseAsZero(): void
    {
        $sut = new DefaultCaster(CastType::BOOL);
        static::assertSame('0', $sut->set(new \stdClass(), false));
    }

    public function testItSerializesString(): void
    {
        $sut = new DefaultCaster(CastType::STRING);
        static::assertSame('test', $sut->set(new \stdClass(), 'test'));
    }

    public function testItSerializesArray(): void
    {
        $sut = new DefaultCaster(CastType::ARRAY);
        static::assertSame('["a"]', $sut->set(new \stdClass(), ['a']));
    }

    public function testItSerializesObject(): void
    {
        $sut = new DefaultCaster(CastType::OBJECT);
        $obj = new \stdClass();
        $obj->key = 'val';
        static::assertSame('{"key":"val"}', $sut->set(new \stdClass(), $obj));
    }

    // ==========================================================================
    // argsForAttribute
    // ==========================================================================

    #[DataProvider('provideTestItReturnsArgsForSupportedCastTypeData')]
    public function testItReturnsArgsForSupportedCastType(CastType $type): void
    {
        static::assertSame([$type], DefaultCaster::argsForAttribute($type, $type->value, null));
    }

    public static function provideTestItReturnsArgsForSupportedCastTypeData(): iterable
    {
        yield 'int' => [CastType::INT];
        yield 'float' => [CastType::FLOAT];
        yield 'bool' => [CastType::BOOL];
        yield 'string' => [CastType::STRING];
        yield 'array' => [CastType::ARRAY];
        yield 'json' => [CastType::JSON];
        yield 'object' => [CastType::OBJECT];
    }

    public function testItReturnsNullForDateCastType(): void
    {
        static::assertNull(DefaultCaster::argsForAttribute(CastType::DATETIME, 'datetime', null));
    }

    public function testItReturnsNullForNullType(): void
    {
        static::assertNull(DefaultCaster::argsForAttribute(null, 'SomeClass', null));
    }

    // ==========================================================================
    // argsForProperty
    // ==========================================================================

    #[DataProvider('provideTestItReturnsArgsForPropertyData')]
    public function testItReturnsArgsForProperty(string $property, CastType $expected): void
    {
        $prop = new \ReflectionProperty(DefaultCasterTestConfig::class, $property);
        static::assertSame([$expected], DefaultCaster::argsForProperty($prop));
    }

    public static function provideTestItReturnsArgsForPropertyData(): iterable
    {
        yield 'int' => ['intProp', CastType::INT];
        yield 'float' => ['floatProp', CastType::FLOAT];
        yield 'bool' => ['boolProp', CastType::BOOL];
        yield 'string' => ['stringProp', CastType::STRING];
        yield 'array' => ['arrayProp', CastType::ARRAY];
        yield 'object' => ['objectProp', CastType::OBJECT];
    }

    public function testItReturnsNullForClassTypedProperty(): void
    {
        $prop = new \ReflectionProperty(DefaultCasterTestConfig::class, 'stdClassProp');
        static::assertNull(DefaultCaster::argsForProperty($prop));
    }
}
