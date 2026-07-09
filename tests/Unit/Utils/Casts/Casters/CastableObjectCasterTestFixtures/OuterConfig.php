<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * One level of nesting — contains an InnerConfig property (automatically inferred).
 */
class OuterConfig extends AbstractCastableObject
{
    public string $name = '';
    public InnerConfig $inner;
}
