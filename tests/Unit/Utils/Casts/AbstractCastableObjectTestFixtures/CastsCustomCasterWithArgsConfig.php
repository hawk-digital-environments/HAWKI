<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\CastedValue;

class CastsCustomCasterWithArgsConfig extends AbstractCastableObject
{
    #[CastedValue(CastsTestCustomCasterWithArgs::class, 'prefixed')]
    public mixed $value = null;
}
