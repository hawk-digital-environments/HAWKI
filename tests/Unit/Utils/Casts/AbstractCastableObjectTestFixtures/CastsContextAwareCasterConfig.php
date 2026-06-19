<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\CastedValue;

class CastsContextAwareCasterConfig extends AbstractCastableObject
{
    public string $locale = 'en';

    #[CastedValue(CastsContextAwareCaster::class)]
    public string $label = '';
}
