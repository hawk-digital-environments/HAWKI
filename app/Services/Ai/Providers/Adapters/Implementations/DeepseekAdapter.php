<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\DeepSeek\Concerns\CreatesDeepSeekClient;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Provider adapter for DeepSeek.
 *
 * Authenticates via API key and discovers models from the DeepSeek REST API,
 * which follows the same `{ "data": [ { "id": "…" }, … ] }` shape as the
 * OpenAI models endpoint.
 *
 * @see https://api-docs.deepseek.com/api/list-models DeepSeek models API
 */
class DeepseekAdapter extends AbstractProviderAdapter
{
    use CreatesDeepSeekClient;

    /**
     * Creates a DeepSeek driver instance authenticated with the provider's API key.
     *
     * Uses the string value of the {@see Lab::DeepSeek} enum because the Laravel AI
     * framework registers DeepSeek under its string key rather than the enum constant.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::DeepSeek->value,
            config: [
                'key' => $provider->api_key,
            ]
        );
    }

    /**
     * Fetches available DeepSeek models from the `/models` endpoint.
     *
     * The response shape mirrors the OpenAI models list: `{ "data": [ { "id": "…" }, … ] }`.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     *
     * @see https://api-docs.deepseek.com/api/list-models
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
