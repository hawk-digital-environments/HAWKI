<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Leaf-level castable object for integration nesting tests.
 */
class CastsNestedInnerConfig extends AbstractCastableObject
{
    public string $value = '';
    public int $num = 0;
}
