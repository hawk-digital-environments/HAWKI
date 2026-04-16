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
}
