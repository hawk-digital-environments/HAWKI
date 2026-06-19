<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters\DateCasterTestFixtures;

/**
 * Fixture exposing date/time properties for DateCaster::argsForProperty reflection tests.
 */
class DateCasterTestConfig
{
    public ?\DateTime $mutableProp = null;
    public ?\DateTimeImmutable $immutableProp = null;
    public ?\DateTimeInterface $interfaceProp = null;
    public int $notADateProp = 0;
    public int|float $unionProp = 0; // union type should be ignored by DateCaster
}
