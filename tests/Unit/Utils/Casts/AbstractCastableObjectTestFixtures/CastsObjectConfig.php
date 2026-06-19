<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\CastedValue;

class CastsObjectConfig extends AbstractCastableObject
{
    #[CastedValue('object')]
    public \stdClass $data;
}
