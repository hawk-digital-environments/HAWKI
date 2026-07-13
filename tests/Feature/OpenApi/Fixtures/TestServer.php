<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi\Fixtures;

use App\JsonApi\V1\Server;

class TestServer extends Server
{
    protected function allSchemas(): array
    {
        return [
            TestingSchema::class,
        ];
    }
}
