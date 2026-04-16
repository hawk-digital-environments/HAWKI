<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Fixture that verifies automatic datetime cast inference for the standard PHP
 * date/time class hierarchy — no #[CastedValue] annotations anywhere.
 *
 * - \DateTime           → 'datetime'           (returns Carbon on hydration)
 * - \DateTimeImmutable  → 'immutable_datetime'  (returns CarbonImmutable on hydration)
 * - \DateTimeInterface  → 'datetime'            (default to mutable; returns Carbon on hydration)
 */
class CastsInferredDateConfig extends AbstractCastableObject
{
    public ?\DateTime $mutable_at = null;

    public ?\DateTimeImmutable $immutable_at = null;

    public ?\DateTimeInterface $interface_at = null;
}
