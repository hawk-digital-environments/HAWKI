<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters\CastableObjectCasterTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Exposes various property types for argsForProperty tests.
 */
class PropertyInspectionFixture
{
    public InnerConfig $castableProp;
    public int $notCastableProp = 0;
    public $untypedProp = null;
}
