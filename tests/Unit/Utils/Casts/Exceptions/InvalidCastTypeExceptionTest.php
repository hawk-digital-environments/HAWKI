<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Exceptions;

use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Exceptions\InvalidCastTypeException;
use App\Utils\Casts\Values\CastType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\Exceptions\InvalidCastTypeExceptionTestFixtures\IntersectionTypePropertyFixture;
use Tests\Unit\Utils\Casts\Exceptions\InvalidCastTypeExceptionTestFixtures\NamedTypePropertyFixture;
use Tests\Unit\Utils\Casts\Exceptions\InvalidCastTypeExceptionTestFixtures\UnionTypePropertyFixture;
use Tests\Unit\Utils\Casts\Exceptions\InvalidCastTypeExceptionTestFixtures\UntypedPropertyFixture;

#[CoversClass(InvalidCastTypeException::class)]
class InvalidCastTypeExceptionTest extends TestCase
{
    // =========================================================================
    // forType
    // =========================================================================

    public function testItForTypeWithCastTypeEnumCaseIncludesEnumValue(): void
    {
        $exception = InvalidCastTypeException::forType(CastType::INT);

        static::assertStringContainsString('"int"', $exception->getMessage());
        static::assertStringContainsString(CastsValue::class, $exception->getMessage());
    }

    public function testItForTypeWithStringIncludesTheString(): void
    {
        $exception = InvalidCastTypeException::forType('not-a-valid-type');

        static::assertStringContainsString('"not-a-valid-type"', $exception->getMessage());
        static::assertStringContainsString(CastsValue::class, $exception->getMessage());
    }

    // =========================================================================
    // forInvalidEncryptedType
    // =========================================================================

    public function testItForInvalidEncryptedTypeIncludesGivenType(): void
    {
        $exception = InvalidCastTypeException::forInvalidEncryptedType('foo', ['bar', 'baz']);

        static::assertStringContainsString('foo', $exception->getMessage());
    }

    public function testItForInvalidEncryptedTypeIncludesValidTypes(): void
    {
        $exception = InvalidCastTypeException::forInvalidEncryptedType('foo', ['bar', 'baz']);

        static::assertStringContainsString('bar, baz', $exception->getMessage());
    }

    // =========================================================================
    // forUndetectableTypeOfProp
    // =========================================================================

    public function testItForUndetectableTypeOfPropWithUnionTypeIncludesClassAndPropertyName(): void
    {
        $prop = new \ReflectionProperty(UnionTypePropertyFixture::class, 'value');
        $exception = InvalidCastTypeException::forUndetectableTypeOfProp($prop);

        static::assertStringContainsString(UnionTypePropertyFixture::class, $exception->getMessage());
        static::assertStringContainsString('$value', $exception->getMessage());
    }

    public function testItForUndetectableTypeOfPropWithIntersectionTypeIncludesClassAndPropertyName(): void
    {
        $prop = new \ReflectionProperty(IntersectionTypePropertyFixture::class, 'value');
        $exception = InvalidCastTypeException::forUndetectableTypeOfProp($prop);

        static::assertStringContainsString(IntersectionTypePropertyFixture::class, $exception->getMessage());
        static::assertStringContainsString('$value', $exception->getMessage());
    }

    // =========================================================================
    // forUncastableTypeOfProp — propertyTypeToString edge cases
    // =========================================================================

    public function testItForUncastableTypeOfPropWithNamedTypeIncludesClassName(): void
    {
        $prop = new \ReflectionProperty(NamedTypePropertyFixture::class, 'value');
        $exception = InvalidCastTypeException::forUncastableTypeOfProp($prop);

        static::assertStringContainsString(\stdClass::class, $exception->getMessage());
    }

    public function testItForUncastableTypeOfPropWithUntypedPropertyIncludesNone(): void
    {
        $prop = new \ReflectionProperty(UntypedPropertyFixture::class, 'value');
        $exception = InvalidCastTypeException::forUncastableTypeOfProp($prop);

        static::assertStringContainsString('"none"', $exception->getMessage());
    }

    public function testItForUncastableTypeOfPropWithUnionTypeIncludesPipeSeparatedTypes(): void
    {
        $prop = new \ReflectionProperty(UnionTypePropertyFixture::class, 'value');
        $exception = InvalidCastTypeException::forUncastableTypeOfProp($prop);

        static::assertStringContainsString('int|string', $exception->getMessage());
    }

    public function testItForUncastableTypeOfPropWithIntersectionTypeIncludesAmpersandSeparatedTypes(): void
    {
        $prop = new \ReflectionProperty(IntersectionTypePropertyFixture::class, 'value');
        $exception = InvalidCastTypeException::forUncastableTypeOfProp($prop);

        static::assertStringContainsString(\ArrayAccess::class . '&' . \Countable::class, $exception->getMessage());
    }
}
