<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Two levels of nesting — wraps OuterConfig to test three-level deep nesting.
 */
class DeepConfig extends AbstractCastableObject
{
    public string $title = '';
    public OuterConfig $outer;
}
