<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi\Drivers\OpenAiExtended\ExtendedOpenAiGatewayTest\ExtendedOpenAiGatewayTestFixtures;

use App\Services\Ai\LaravelAi\Drivers\OpenAiExtended\ExtendedOpenAiGateway;
use Laravel\Ai\Providers\Provider;

/**
 * Test double exposing the protected stream processing so the SSE handling
 * can be exercised without going through the full HTTP request path.
 */
class ExposedGateway extends ExtendedOpenAiGateway
{
    public function exposeProcessTextStream(
        string $invocationId,
        Provider $provider,
        string $model,
        $streamBody,
    ): \Generator {
        return $this->processTextStream($invocationId, $provider, $model, $streamBody);
    }
}
