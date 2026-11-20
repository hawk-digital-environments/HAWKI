<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi\Fixtures;

enum TestingStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
