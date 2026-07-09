<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Anthropic\Concerns\CreatesAnthropicClient;
use Laravel\Ai\Providers\Provider as Driver;


/**
 * Provider adapter for Anthropic (Claude models).
 *
 * Builds the Laravel AI Anthropic driver using the provider's stored API key and
 * fetches the available model list from the Anthropic REST API.
 *
 * @see https://platform.claude.com/docs/en/api/models/list Anthropic models API
 */
class AnthropicAdapter extends AbstractProviderAdapter
{
    use CreatesAnthropicClient;

    /**
     * Creates an Anthropic driver instance authenticated with the provider's API key.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Anthropic,
            config: [
                'key' => $provider->api_key,
            ]
        );
    }

    /**
     * Fetches available Claude models from the Anthropic API.
     *
     * The response shape is `{ "data": [ { "id": "claude-...", … }, … ] }`.
     * Each entry is mapped to an unsaved {@see \App\Models\Ai\AiModel} instance.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     *
     * @see https://platform.claude.com/docs/en/api/models/list
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->createModelListClient($this->client($provider->driver))
            ->get('/models')
            ->getMapped('data.*', function ($item) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: data_get($item, 'id'),
                    provider: $provider,
                );
            });
    }
}
