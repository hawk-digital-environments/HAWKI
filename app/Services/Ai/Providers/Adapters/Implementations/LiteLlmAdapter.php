<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\LaravelAi\Drivers\LiteLlm\LiteLlmGateway;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use Illuminate\Events\Dispatcher;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Provider adapter for LiteLLM proxies exposing the OpenAI Responses API.
 *
 * Behaves like {@see OpenAiLikeAdapter} (OpenAI driver pointed at the provider's
 * `api_url`) but routes requests through {@see LiteLlmGateway}, which reshapes replayed
 * assistant messages into the strict output item form that LiteLLM's downstream
 * backends (e.g. vLLM) insist on. Use this adapter instead of `openai_like` whenever a
 * conversation with history is answered with HTTP 400 "validation errors" by a LiteLLM
 * endpoint.
 */
class LiteLlmAdapter extends OpenAiLikeAdapter
{
    /**
     * Creates an OpenAI driver that uses {@see LiteLlmGateway} for request mapping.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::OpenAI,
            config: [
                'key' => $provider->api_key,
                'url' => $this->baseUrl ?? $provider->api_url,
            ],
            builder: function (Dispatcher $dispatcher, array $config) {
                return new OpenAiProvider(
                    gateway: new LiteLlmGateway($dispatcher),
                    config: $config,
                    events: $dispatcher
                );
            }
        );
    }
}
