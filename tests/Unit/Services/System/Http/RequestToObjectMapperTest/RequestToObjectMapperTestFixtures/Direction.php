<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Http\RequestToObjectMapperTest\RequestToObjectMapperTestFixtures;

/**
 * Unit enum (no backing value) for mapper coercion tests — stored by case name.
 */
enum Direction
{
    case North;
    case South;
}
