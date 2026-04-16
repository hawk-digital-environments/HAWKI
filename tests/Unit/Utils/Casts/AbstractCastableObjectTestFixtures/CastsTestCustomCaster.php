<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\Contracts\CastsValue;

class CastsTestCustomCaster implements CastsValue
{
    public function get(object $object, string $stored): mixed
    {
        return 'custom:' . $stored;
    }

    public function set(object $object, mixed $value): string
    {
        return str_replace('custom:', '', $value);
    }
}
