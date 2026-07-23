<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Mid-level castable object for integration nesting tests.
 */
class CastsNestedOuterConfig extends AbstractCastableObject
{
    public string $tag = '';
    public CastsNestedInnerConfig $inner;
}
