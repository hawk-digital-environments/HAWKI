<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Traits\OpenAiModelListTrait;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\OpenRouter\Concerns\CreatesOpenRouterClient;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Provider adapter for OpenRouter (openrouter.ai).
 *
 * OpenRouter is a meta-provider that proxies requests to multiple underlying models
 * (GPT-4, Claude, Llama, etc.) through a single OpenAI-compatible API. Authentication
 * uses an OpenRouter API key; the model list is fetched from OpenRouter's `/models`
 * endpoint, which follows the standard OpenAI `{ "data": [ { "id": "…" }, … ] }` shape.
 */
class OpenRouterAdapter extends AbstractProviderAdapter
{
    use CreatesOpenRouterClient;
    use OpenAiModelListTrait;

    /**
     * Creates an OpenRouter driver instance authenticated with the provider's API key.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::OpenRouter,
            config: [
                'key' => $provider->api_key,
            ]
        );
    }

    /**
     * Fetches available models from OpenRouter's `/models` endpoint.
     *
     * Returns all models OpenRouter exposes for the configured API key, which may
     * include models from many underlying providers aggregated through the proxy.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList($provider, $this->createModelListClient($this->client($provider->driver)));
    }
}
