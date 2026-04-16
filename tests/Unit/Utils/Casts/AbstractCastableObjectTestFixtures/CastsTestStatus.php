<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

enum CastsTestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
