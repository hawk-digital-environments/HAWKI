<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

class CastsEnumConfig extends AbstractCastableObject
{
    public CastsTestStatus $status = CastsTestStatus::Active;
    public CastsTestDirection $direction = CastsTestDirection::North;
}
