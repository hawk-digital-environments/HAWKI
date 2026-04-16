<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\Contracts\CastsValue;

class CastsContextAwareCaster implements CastsValue
{
    public function get(object $object, string $stored): mixed
    {
        return ($object->locale ?? 'en') . ':' . $stored;
    }

    public function set(object $object, mixed $value): string
    {
        return (string)$value;
    }
}
