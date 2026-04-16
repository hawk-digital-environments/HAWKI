<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\Contracts\CastsValue;

class CastsTestCustomCasterWithArgs implements CastsValue
{
    public function __construct(private readonly string $prefix)
    {
    }

    public function get(object $object, string $stored): mixed
    {
        return $this->prefix . ':' . $stored;
    }

    public function set(object $object, mixed $value): string
    {
        return str_replace($this->prefix . ':', '', $value);
    }
}
