<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

class CastsStaticPropertyConfig extends AbstractCastableObject
{
    public static string $ignored = 'static-default';
    public string $name = 'default';
}
