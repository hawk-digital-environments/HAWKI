<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

/**
 * Int-backed enum fixture — exercises the backing-type coercion in the EnumCaster.
 */
enum CastsTestPriority: int
{
    case Low = 1;
    case High = 2;
}
