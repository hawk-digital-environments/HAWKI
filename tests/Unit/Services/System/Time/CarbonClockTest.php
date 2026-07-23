<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Time;

use App\Services\System\Time\CarbonClock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;

#[CoversClass(CarbonClock::class)]
class CarbonClockTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new CarbonClock();

        static::assertInstanceOf(CarbonClock::class, $sut);
    }

    public function testItConstructsWithDateTimeImmutable(): void
    {
        $sut = new CarbonClock(new \DateTimeImmutable('2024-01-15 12:00:00'));

        static::assertInstanceOf(CarbonClock::class, $sut);
    }

    public function testItConstructsWithDateTime(): void
    {
        $sut = new CarbonClock(new \DateTime('2024-01-15 12:00:00'));

        static::assertInstanceOf(CarbonClock::class, $sut);
    }

    public function testItConstructsWithAnotherClockInterface(): void
    {
        $inner = new MockClock('2024-01-15 12:00:00');
        $sut = new CarbonClock($inner);

        static::assertInstanceOf(CarbonClock::class, $sut);
    }

    // =========================================================================
    // now() — return type
    // =========================================================================

    public function testItNowReturnsCarbonImmutable(): void
    {
        $sut = new CarbonClock(new \DateTimeImmutable('2024-01-15 12:00:00'));

        static::assertInstanceOf(CarbonImmutable::class, $sut->now());
    }

    // =========================================================================
    // now() — DateTimeImmutable source (frozen time)
    // =========================================================================

    public function testItNowReturnsFixedTimeFromDateTimeImmutable(): void
    {
        $frozen = new \DateTimeImmutable('2024-01-15 12:00:00 UTC');
        $sut = new CarbonClock($frozen);

        static::assertSame('2024-01-15 12:00:00', $sut->now()->format('Y-m-d H:i:s'));
    }

    public function testItNowReturnsSameValueOnEveryCallWhenFrozen(): void
    {
        $sut = new CarbonClock(new \DateTimeImmutable('2024-06-01 08:30:00 UTC'));

        static::assertSame($sut->now()->timestamp, $sut->now()->timestamp);
    }

    // =========================================================================
    // now() — DateTime source
    // =========================================================================

    public function testItNowReturnsFixedTimeFromDateTime(): void
    {
        $dt = new \DateTime('2024-03-10 09:15:00 UTC');
        $sut = new CarbonClock($dt);

        static::assertSame('2024-03-10 09:15:00', $sut->now()->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // now() — ClockInterface source
    // =========================================================================

    public function testItDelegatesToInnerClockInterface(): void
    {
        $inner = new MockClock('2024-05-20 14:00:00');
        $sut = new CarbonClock($inner);

        static::assertSame('2024-05-20 14:00:00', $sut->now()->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // now() — timezone override
    // =========================================================================

    public function testItAppliesTimezoneToNow(): void
    {
        $utcTime = new \DateTimeImmutable('2024-01-15 12:00:00 UTC');
        $berlin = new \DateTimeZone('Europe/Berlin');
        $sut = new CarbonClock($utcTime, $berlin);

        static::assertSame('Europe/Berlin', $sut->now()->timezone->getName());
    }

    public function testItConvertsTimeToGivenTimezone(): void
    {
        $utcTime = new \DateTimeImmutable('2024-01-15 12:00:00 UTC');
        $berlin = new \DateTimeZone('Europe/Berlin');
        $sut = new CarbonClock($utcTime, $berlin);

        // Europe/Berlin is UTC+1 in winter; 12:00 UTC = 13:00 Berlin.
        static::assertSame('13:00:00', $sut->now()->format('H:i:s'));
    }

    public function testItDefaultsToSystemClockWhenNullPassed(): void
    {
        $sut = new CarbonClock();
        $before = new \DateTimeImmutable();

        $result = $sut->now();

        $after = new \DateTimeImmutable();
        static::assertGreaterThanOrEqual($before->getTimestamp(), $result->getTimestamp());
        static::assertLessThanOrEqual($after->getTimestamp(), $result->getTimestamp());
    }
}
