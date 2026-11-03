<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGatewayStepTest\StallAwareOpenAiCompatibleGatewayStepTestFixtures;

use App\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGateway;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Providers\Provider;

/**
 * Gateway spy that records the options of every HTTP client it builds.
 */
class ClientSpyingGateway extends StallAwareOpenAiCompatibleGateway
{
    /** @var list<array<string, mixed>> The options of every HTTP client built by the gateway. */
    public array $capturedOptions = [];

    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        $request = parent::client($provider, $timeout);

        $this->capturedOptions[] = $request->getOptions();

        return $request;
    }
}
