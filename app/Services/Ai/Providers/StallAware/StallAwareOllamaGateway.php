<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\StallAware;


use App\Services\Ai\Providers\StallAware\Concerns\AppliesStallDetection;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Gateway\Ollama\OllamaGateway;
use Laravel\Ai\Providers\Provider;

/**
 * {@see OllamaGateway} with curl stall detection on its requests.
 *
 * The only change to the parent gateway is {@see client()}: the built HTTP client additionally
 * receives the provider's stall timeout via {@see AppliesStallDetection}. Streamed requests are
 * additionally guarded through the prompt timeout that {@see \App\Services\Ai\Agents\Values\AgentTimeouts::forStream()}
 * passes down. This mirrors the way stall detection support is intended to land in the upstream
 * `laravel/ai` package — once it is supported natively, this class and its registration in
 * {@see \App\Providers\AiServiceProvider} can be removed.
 */
class StallAwareOllamaGateway extends OllamaGateway
{
    use AppliesStallDetection;

    /**
     * @inheritDoc
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        return $this->withStallDetection(parent::client($provider, $timeout), $provider);
    }
}
