<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

class CastsInvalidUnionConfig extends AbstractCastableObject
{
    public int|string $value = 0;
}
