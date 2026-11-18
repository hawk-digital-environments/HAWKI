<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGatewayTest\StallAwareOpenAiCompatibleGatewayTestFixtures;

use App\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGateway;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Providers\Provider;

/**
 * Exposes the protected client construction of {@see StallAwareOpenAiCompatibleGateway}.
 */
class ExposingStallAwareGateway extends StallAwareOpenAiCompatibleGateway
{
    public function exposeClient(Provider $provider, int|null $timeout = null): PendingRequest
    {
        return $this->client($provider, $timeout);
    }
}
