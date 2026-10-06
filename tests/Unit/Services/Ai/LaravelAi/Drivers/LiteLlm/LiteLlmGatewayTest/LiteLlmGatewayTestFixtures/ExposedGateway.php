<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi\Drivers\LiteLlm\LiteLlmGatewayTest\LiteLlmGatewayTestFixtures;

use App\Services\Ai\LaravelAi\Drivers\LiteLlm\LiteLlmGateway;
use Laravel\Ai\Providers\Provider;

/**
 * Test double exposing the protected message mapping so the request input can be
 * inspected without going through the full HTTP request path.
 */
class ExposedGateway extends LiteLlmGateway
{
    public function exposeMapMessagesToInput(array $messages, ?string $instructions, Provider $provider): array
    {
        return $this->mapMessagesToInput($messages, $instructions, $provider);
    }
}
