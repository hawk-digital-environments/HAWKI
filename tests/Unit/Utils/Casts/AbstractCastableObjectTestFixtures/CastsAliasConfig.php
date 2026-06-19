<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\CastedValue;

class CastsAliasConfig extends AbstractCastableObject
{
    #[CastedValue('integer')]
    public int $integer_val = 0;

    #[CastedValue('double')]
    public float $double_val = 0.0;

    #[CastedValue('real')]
    public float $real_val = 0.0;

    #[CastedValue('boolean')]
    public bool $boolean_val = false;

    #[CastedValue('json')]
    public array $json_val = [];
}
