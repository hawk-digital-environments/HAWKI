<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts;

use App\Utils\Casts\CastedValue;
use App\Utils\Casts\Casters\DateCaster;
use App\Utils\Casts\Casters\DefaultCaster;
use App\Utils\Casts\Casters\EncryptedCaster;
use App\Utils\Casts\Exceptions\AmbiguousFormatArgumentException;
use App\Utils\Casts\Exceptions\InvalidCastTypeException;
use App\Utils\Casts\Values\CastType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsContextAwareCaster;

#[CoversClass(CastedValue::class)]
#[CoversClass(AmbiguousFormatArgumentException::class)]
#[CoversClass(InvalidCastTypeException::class)]
class CastedValueTest extends TestCase
{
    public function testItConstructs(): void
    {
        $sut = new CastedValue('int');
        static::assertInstanceOf(CastedValue::class, $sut);
    }

    public function testItResolvesBuiltinTypeStringToDefaultCaster(): void
    {
        $sut = new CastedValue('int');
        static::assertSame(DefaultCaster::class, $sut->caster->casterClass);
        static::assertSame([CastType::INT], $sut->caster->args);
    }

    public function testItAcceptsCastTypeEnumDirectly(): void
    {
        $sut = new CastedValue(CastType::INT);
        static::assertSame(DefaultCaster::class, $sut->caster->casterClass);
        static::assertSame([CastType::INT], $sut->caster->args);
    }

    public function testItResolvesDatetimeStringToDateCaster(): void
    {
        $sut = new CastedValue('datetime');
        static::assertSame(DateCaster::class, $sut->caster->casterClass);
        static::assertSame([CastType::DATETIME, null], $sut->caster->args);
    }

    public function testItExtractsFormatFromParameterisedTypeString(): void
    {
        $sut = new CastedValue('datetime:d.m.Y H:i');
        static::assertSame(DateCaster::class, $sut->caster->casterClass);
        static::assertSame([CastType::DATETIME, 'd.m.Y H:i'], $sut->caster->args);
    }

    public function testItAcceptsFormatAsSecondArgument(): void
    {
        $sut = new CastedValue(CastType::DATETIME, 'd.m.Y H:i');
        static::assertSame(DateCaster::class, $sut->caster->casterClass);
        static::assertSame([CastType::DATETIME, 'd.m.Y H:i'], $sut->caster->args);
    }

    public function testItResolvesEncryptedTypeStringToEncryptedCaster(): void
    {
        $sut = new CastedValue('encrypted:string');
        static::assertSame(EncryptedCaster::class, $sut->caster->casterClass);
        static::assertSame([CastType::STRING], $sut->caster->args);
    }

    public function testItResolvesCustomCasterClassDirectly(): void
    {
        $sut = new CastedValue(CastsContextAwareCaster::class);
        static::assertSame(CastsContextAwareCaster::class, $sut->caster->casterClass);
    }

    public function testItPassesFormatAsConstructorArgToCustomCaster(): void
    {
        $sut = new CastedValue(CastsContextAwareCaster::class . ':some_arg');
        static::assertSame(['some_arg'], $sut->caster->args);
    }

    public function testItThrowsWhenFormatProvidedBothInTypeStringAndAsArgument(): void
    {
        $this->expectException(AmbiguousFormatArgumentException::class);
        $this->expectExceptionMessage(
            'Format/argument string was provided both inside the type string ("datetime") and as the second constructor argument ("another_format"). Use one or the other, not both.'
        );
        new CastedValue('datetime:d.m.Y H:i', 'another_format');
    }

    public function testItThrowsForInvalidType(): void
    {
        $this->expectException(InvalidCastTypeException::class);
        new CastedValue('not_a_valid_type');
    }

    public function testItThrowsForInvalidEncryptedSubType(): void
    {
        $this->expectException(InvalidCastTypeException::class);
        new CastedValue('encrypted:not_a_valid_type');
    }
}
