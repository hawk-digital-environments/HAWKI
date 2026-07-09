<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Time;

use App\Services\System\Time\Clock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;

#[CoversClass(Clock::class)]
class ClockTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new Clock();

        static::assertInstanceOf(Clock::class, $sut);
    }

    public function testItConstructsWithDateTimeImmutable(): void
    {
        $sut = new Clock(new \DateTimeImmutable('2024-01-15 12:00:00'));

        static::assertInstanceOf(Clock::class, $sut);
    }

    public function testItConstructsWithDateTime(): void
    {
        $sut = new Clock(new \DateTime('2024-01-15 12:00:00'));

        static::assertInstanceOf(Clock::class, $sut);
    }

    public function testItConstructsWithAnotherClockInterface(): void
    {
        $inner = new MockClock('2024-01-15 12:00:00');
        $sut = new Clock($inner);

        static::assertInstanceOf(Clock::class, $sut);
    }

    // =========================================================================
    // now() — return type
    // =========================================================================

    public function testItNowReturnsCarbonImmutable(): void
    {
        $sut = new Clock(new \DateTimeImmutable('2024-01-15 12:00:00'));

        static::assertInstanceOf(CarbonImmutable::class, $sut->now());
    }

    // =========================================================================
    // now() — DateTimeImmutable source (frozen time)
    // =========================================================================

    public function testItNowReturnsFixedTimeFromDateTimeImmutable(): void
    {
        $frozen = new \DateTimeImmutable('2024-01-15 12:00:00 UTC');
        $sut = new Clock($frozen);

        static::assertSame('2024-01-15 12:00:00', $sut->now()->format('Y-m-d H:i:s'));
    }

    public function testItNowReturnsSameValueOnEveryCallWhenFrozen(): void
    {
        $sut = new Clock(new \DateTimeImmutable('2024-06-01 08:30:00 UTC'));

        static::assertSame($sut->now()->timestamp, $sut->now()->timestamp);
    }

    // =========================================================================
    // now() — DateTime source
    // =========================================================================

    public function testItNowReturnsFixedTimeFromDateTime(): void
    {
        $dt = new \DateTime('2024-03-10 09:15:00 UTC');
        $sut = new Clock($dt);

        static::assertSame('2024-03-10 09:15:00', $sut->now()->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // now() — ClockInterface source
    // =========================================================================

    public function testItDelegatesToInnerClockInterface(): void
    {
        $inner = new MockClock('2024-05-20 14:00:00');
        $sut = new Clock($inner);

        static::assertSame('2024-05-20 14:00:00', $sut->now()->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // now() — timezone override
    // =========================================================================

    public function testItAppliesTimezoneToNow(): void
    {
        $utcTime = new \DateTimeImmutable('2024-01-15 12:00:00 UTC');
        $berlin = new \DateTimeZone('Europe/Berlin');
        $sut = new Clock($utcTime, $berlin);

        static::assertSame('Europe/Berlin', $sut->now()->timezone->getName());
    }

    public function testItConvertsTimeToGivenTimezone(): void
    {
        $utcTime = new \DateTimeImmutable('2024-01-15 12:00:00 UTC');
        $berlin = new \DateTimeZone('Europe/Berlin');
        $sut = new Clock($utcTime, $berlin);

        // Europe/Berlin is UTC+1 in winter; 12:00 UTC = 13:00 Berlin.
        static::assertSame('13:00:00', $sut->now()->format('H:i:s'));
    }

    public function testItDefaultsToSystemClockWhenNullPassed(): void
    {
        $sut = new Clock();
        $before = new \DateTimeImmutable();

        $result = $sut->now();

        $after = new \DateTimeImmutable();
        static::assertGreaterThanOrEqual($before->getTimestamp(), $result->getTimestamp());
        static::assertLessThanOrEqual($after->getTimestamp(), $result->getTimestamp());
    }
}
