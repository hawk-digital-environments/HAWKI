<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\StallAware\StallAwareOllamaGatewayTest\StallAwareOllamaGatewayTestFixtures;

use App\Services\Ai\Providers\StallAware\StallAwareOllamaGateway;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Providers\Provider;

/**
 * Exposes the protected client construction of {@see StallAwareOllamaGateway}.
 */
class ExposingStallAwareGateway extends StallAwareOllamaGateway
{
    public function exposeClient(Provider $provider, int|null $timeout = null): PendingRequest
    {
        return $this->client($provider, $timeout);
    }
}
