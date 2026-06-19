<?php
declare(strict_types=1);


namespace App\Services\System\Time;


use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A wrapper around a clock or a specific time that can be used to get the current time.
 * This allows for easier testing and flexibility in how the current time is determined.
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
     * @inheritDoc
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
