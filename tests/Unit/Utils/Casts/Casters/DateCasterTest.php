<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters;

use App\Utils\Casts\Casters\DateCaster;
use App\Utils\Casts\Values\CastType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Utils\Casts\Casters\DateCasterTestFixtures\DateCasterTestConfig;

#[CoversClass(DateCaster::class)]
class DateCasterTest extends TestCase
{
    // ==========================================================================
    // Hydration — get()
    // ==========================================================================

    public function testItHydratesDate(): void
    {
        $sut = new DateCaster(CastType::DATE);
        $result = $sut->get(new \stdClass(), '2024-03-15');
        static::assertInstanceOf(Carbon::class, $result);
        static::assertSame('2024-03-15 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testItHydratesImmutableDate(): void
    {
        $sut = new DateCaster(CastType::IMMUTABLE_DATE);
        $result = $sut->get(new \stdClass(), '2024-03-15');
        static::assertInstanceOf(CarbonImmutable::class, $result);
        static::assertSame('2024-03-15 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testItHydratesDatetime(): void
    {
        $sut = new DateCaster(CastType::DATETIME);
        $result = $sut->get(new \stdClass(), '2024-03-15 12:30:00');
        static::assertInstanceOf(Carbon::class, $result);
        static::assertSame('2024-03-15 12:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testItHydratesImmutableDatetime(): void
    {
        $sut = new DateCaster(CastType::IMMUTABLE_DATETIME);
        $result = $sut->get(new \stdClass(), '2024-03-15 12:30:00');
        static::assertInstanceOf(CarbonImmutable::class, $result);
        static::assertSame('2024-03-15 12:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testItHydratesDatetimeWithCustomFormat(): void
    {
        $sut = new DateCaster(CastType::DATETIME, 'd.m.Y H:i');
        $result = $sut->get(new \stdClass(), '15.03.2024 12:30');
        static::assertInstanceOf(Carbon::class, $result);
        static::assertSame('2024-03-15 12:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testItHydratesImmutableDatetimeWithCustomFormat(): void
    {
        $sut = new DateCaster(CastType::IMMUTABLE_DATETIME, 'd.m.Y H:i');
        $result = $sut->get(new \stdClass(), '15.03.2024 12:30');
        static::assertInstanceOf(CarbonImmutable::class, $result);
        static::assertSame('2024-03-15 12:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testItHydratesTimestampAsInt(): void
    {
        $sut = new DateCaster(CastType::TIMESTAMP);
        static::assertSame(1710499800, $sut->get(new \stdClass(), '1710499800'));
    }

    // ==========================================================================
    // Serialization — set()
    // ==========================================================================

    public function testItSerializesDate(): void
    {
        $sut = new DateCaster(CastType::DATE);
        static::assertSame('2024-03-15', $sut->set(new \stdClass(), Carbon::parse('2024-03-15')));
    }

    public function testItSerializesImmutableDate(): void
    {
        $sut = new DateCaster(CastType::IMMUTABLE_DATE);
        static::assertSame('2024-03-15', $sut->set(new \stdClass(), CarbonImmutable::parse('2024-03-15')));
    }

    public function testItSerializesDatetime(): void
    {
        $sut = new DateCaster(CastType::DATETIME);
        static::assertSame('2024-03-15 12:30:00', $sut->set(new \stdClass(), Carbon::parse('2024-03-15 12:30:00')));
    }

    public function testItSerializesDatetimeWithCustomFormat(): void
    {
        $sut = new DateCaster(CastType::DATETIME, 'd.m.Y H:i');
        static::assertSame('15.03.2024 12:30', $sut->set(new \stdClass(), Carbon::parse('2024-03-15 12:30:00')));
    }

    public function testItSerializesTimestampFromInt(): void
    {
        $sut = new DateCaster(CastType::TIMESTAMP);
        static::assertSame('1710499800', $sut->set(new \stdClass(), 1710499800));
    }

    public function testItSerializesTimestampFromDateTimeInterface(): void
    {
        $sut = new DateCaster(CastType::TIMESTAMP);
        $dt = Carbon::createFromTimestamp(1710499800);
        static::assertSame('1710499800', $sut->set(new \stdClass(), $dt));
    }

    // ==========================================================================
    // argsForAttribute
    // ==========================================================================

    #[DataProvider('provideTestItReturnsArgsForDateCastTypeData')]
    public function testItReturnsArgsForDateCastType(CastType $type): void
    {
        $result = DateCaster::argsForAttribute($type, $type->value, null);
        static::assertSame([$type, null], $result);
    }

    public static function provideTestItReturnsArgsForDateCastTypeData(): iterable
    {
        yield 'date' => [CastType::DATE];
        yield 'immutable_date' => [CastType::IMMUTABLE_DATE];
        yield 'datetime' => [CastType::DATETIME];
        yield 'immutable_datetime' => [CastType::IMMUTABLE_DATETIME];
        yield 'timestamp' => [CastType::TIMESTAMP];
    }

    public function testItIncludesFormatInArgsWhenProvided(): void
    {
        $result = DateCaster::argsForAttribute(CastType::DATETIME, 'datetime', 'd.m.Y H:i');
        static::assertSame([CastType::DATETIME, 'd.m.Y H:i'], $result);
    }

    public function testItReturnsNullForNonDateCastType(): void
    {
        static::assertNull(DateCaster::argsForAttribute(CastType::INT, 'int', null));
    }

    public function testItReturnsNullForNullType(): void
    {
        static::assertNull(DateCaster::argsForAttribute(null, 'SomeClass', null));
    }

    // ==========================================================================
    // argsForProperty
    // ==========================================================================

    public function testItReturnsDatetimeArgsForDateTimeProperty(): void
    {
        $prop = new \ReflectionProperty(DateCasterTestConfig::class, 'mutableProp');
        static::assertSame([CastType::DATETIME], DateCaster::argsForProperty($prop));
    }

    public function testItReturnsImmutableDatetimeArgsForDateTimeImmutableProperty(): void
    {
        $prop = new \ReflectionProperty(DateCasterTestConfig::class, 'immutableProp');
        static::assertSame([CastType::IMMUTABLE_DATETIME], DateCaster::argsForProperty($prop));
    }

    public function testItReturnsDatetimeArgsForDateTimeInterfaceProperty(): void
    {
        $prop = new \ReflectionProperty(DateCasterTestConfig::class, 'interfaceProp');
        static::assertSame([CastType::DATETIME], DateCaster::argsForProperty($prop));
    }

    public function testItReturnsNullForNonDateProperty(): void
    {
        $prop = new \ReflectionProperty(DateCasterTestConfig::class, 'notADateProp');
        static::assertNull(DateCaster::argsForProperty($prop));
    }
}
