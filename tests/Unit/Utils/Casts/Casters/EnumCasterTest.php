<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters;

use App\Utils\Casts\Casters\EnumCaster;
use App\Utils\Casts\Values\CastType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsTestDirection;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsTestStatus;
use Tests\Unit\Utils\Casts\Casters\EnumCasterTestFixtures\EnumCasterTestConfig;

#[CoversClass(EnumCaster::class)]
class EnumCasterTest extends TestCase
{
    // ==========================================================================
    // Hydration — get()
    // ==========================================================================

    public function testItHydratesBackedEnum(): void
    {
        $sut = new EnumCaster(CastsTestStatus::class);
        static::assertSame(CastsTestStatus::Inactive, $sut->get(new \stdClass(), 'inactive', 'prop'));
    }

    public function testItHydratesUnitEnumByCaseName(): void
    {
        $sut = new EnumCaster(CastsTestDirection::class);
        static::assertSame(CastsTestDirection::South, $sut->get(new \stdClass(), 'South', 'prop'));
    }

    // ==========================================================================
    // Serialization — set()
    // ==========================================================================

    public function testItSerializesBackedEnumByValue(): void
    {
        $sut = new EnumCaster(CastsTestStatus::class);
        static::assertSame('active', $sut->set(new \stdClass(), CastsTestStatus::Active, 'prop'));
    }

    public function testItSerializesUnitEnumByCaseName(): void
    {
        $sut = new EnumCaster(CastsTestDirection::class);
        static::assertSame('North', $sut->set(new \stdClass(), CastsTestDirection::North, 'prop'));
    }

    public function testItSerializesNonEnumValueAsEmptyString(): void
    {
        $sut = new EnumCaster(CastsTestStatus::class);
        static::assertSame('', $sut->set(new \stdClass(), 'not-an-enum', 'prop'));
    }

    // ==========================================================================
    // argsForAttribute
    // ==========================================================================

    public function testItReturnsEnumClassArgsForEnumTypeString(): void
    {
        $result = EnumCaster::argsForAttribute(null, CastsTestStatus::class, null);
        static::assertSame([CastsTestStatus::class], $result);
    }

    public function testItReturnsNullWhenCastTypeIsResolved(): void
    {
        static::assertNull(EnumCaster::argsForAttribute(CastType::INT, 'int', null));
    }

    public function testItReturnsNullForNonEnumClassString(): void
    {
        static::assertNull(EnumCaster::argsForAttribute(null, \stdClass::class, null));
    }

    public function testItReturnsNullForUnknownString(): void
    {
        static::assertNull(EnumCaster::argsForAttribute(null, 'NotAClass', null));
    }

    // ==========================================================================
    // argsForProperty
    // ==========================================================================

    public function testItReturnsEnumClassArgsForBackedEnumProperty(): void
    {
        $prop = new \ReflectionProperty(EnumCasterTestConfig::class, 'statusProp');
        static::assertSame([CastsTestStatus::class], EnumCaster::argsForProperty($prop));
    }

    public function testItReturnsEnumClassArgsForUnitEnumProperty(): void
    {
        $prop = new \ReflectionProperty(EnumCasterTestConfig::class, 'directionProp');
        static::assertSame([CastsTestDirection::class], EnumCaster::argsForProperty($prop));
    }

    public function testItReturnsNullForBuiltinTypeProperty(): void
    {
        $prop = new \ReflectionProperty(EnumCasterTestConfig::class, 'notAnEnumProp');
        static::assertNull(EnumCaster::argsForProperty($prop));
    }

    public function testItReturnsNullForUntypedProperty(): void
    {
        $prop = new \ReflectionProperty(EnumCasterTestConfig::class, 'notTypedProp');
        static::assertNull(EnumCaster::argsForProperty($prop));
    }
}
