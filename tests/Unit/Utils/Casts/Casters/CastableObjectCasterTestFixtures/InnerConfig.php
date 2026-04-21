<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Simple leaf-level castable object used as a nested property in outer fixture configs.
 */
class InnerConfig extends AbstractCastableObject
{
    public string $label = '';
    public int $count = 0;
}
