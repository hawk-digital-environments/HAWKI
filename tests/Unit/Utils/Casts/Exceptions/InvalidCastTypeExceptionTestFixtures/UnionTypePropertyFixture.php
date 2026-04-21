<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Exceptions\InvalidCastTypeExceptionTestFixtures;

class UnionTypePropertyFixture
{
    public int|string $value = 0;
}
