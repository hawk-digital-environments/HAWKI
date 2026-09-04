<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Http\RequestToObjectMapperTest\RequestToObjectMapperTestFixtures;

/**
 * Int-backed enum for mapper coercion tests.
 */
enum IntStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
