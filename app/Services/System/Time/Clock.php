<?php
declare(strict_types=1);


namespace App\Services\System\Time;


use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * HAWKI's concrete {@see ClockInterface} implementation, always returning {@see CarbonImmutable}.
 *
 * Wraps any of three underlying time sources:
 * - Another {@see ClockInterface} (delegates to its `now()`) — default is {@see \Symfony\Component\Clock\Clock}.
 * - A {@see DateTimeImmutable} — returned as-is, effectively freezing time (useful in tests).
 * - A {@see DateTime} — converted to {@see CarbonImmutable} on every call.
 *
 * An optional `$timezone` applied to every `now()` call lets services read "current time" in a
 * specific time zone without having to convert manually.
 *
 * Usage in tests (freeze time):
 * ```php
 * $frozenAt = new \DateTimeImmutable('2024-01-15 12:00:00');
 * $clock = new Clock($frozenAt);
 * $service = new MyService($clock);
 *
 * static::assertSame('2024-01-15', $service->getTodayString());
 * ```
 */
readonly class Clock implements ClockInterface
{
    private ClockInterface|DateTimeImmutable|DateTime $clockOrTime;

    public function __construct(
        ClockInterface|DateTimeImmutable|DateTime|null $clockOrTime = null,
        private ?\DateTimeZone                         $timezone = null,
    )
    {
        $this->clockOrTime = $clockOrTime ?? new \Symfony\Component\Clock\Clock(timezone: $timezone);
    }

    /**
     * Returns the current time as a {@see CarbonImmutable}.
     * When a timezone was provided at construction, the result is converted to that timezone.
     */
    public function now(): CarbonImmutable
    {
        $now = match (true) {
            $this->clockOrTime instanceof ClockInterface => $this->clockOrTime->now(),
            $this->clockOrTime instanceof DateTimeImmutable => $this->clockOrTime,
            $this->clockOrTime instanceof DateTime => CarbonImmutable::instance($this->clockOrTime),
        };

        return isset($this->timezone)
            ? CarbonImmutable::instance($now)->setTimezone($this->timezone)
            : CarbonImmutable::instance($now);
    }
}
