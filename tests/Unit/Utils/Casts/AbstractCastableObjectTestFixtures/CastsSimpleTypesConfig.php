<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

class CastsSimpleTypesConfig extends AbstractCastableObject
{
    public int $count = 0;
    public float $price = 0.0;
    public bool $active = false;
    public string $name = '';
    public array $tags = [];
}
