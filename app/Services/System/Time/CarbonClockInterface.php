<?php
declare(strict_types=1);


namespace App\Services\System\Time;


use Carbon\CarbonImmutable;
use Psr\Clock\ClockInterface;

/**
 * The same as {@see ClockInterface}, but always returns {@see CarbonImmutable} instead of {@see DateTimeImmutable}.
 */
interface CarbonClockInterface extends ClockInterface
{
    public function now(): CarbonImmutable;
}
