<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters\EnumCasterTestFixtures;

use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsTestDirection;
use Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures\CastsTestStatus;

/**
 * Fixture exposing enum-typed and non-enum properties for EnumCaster::argsForProperty tests.
 */
class EnumCasterTestConfig
{
    public CastsTestStatus $statusProp;
    public CastsTestDirection $directionProp;
    public int $notAnEnumProp = 0;
    public $notTypedProp = 0;
}
