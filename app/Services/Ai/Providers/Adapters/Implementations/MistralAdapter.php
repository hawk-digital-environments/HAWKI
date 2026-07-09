<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Mistral\Concerns\CreatesMistralClient;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Provider adapter for Mistral AI.
 *
 * Authenticates via API key and fetches the model list from the Mistral REST API.
 * The Mistral `/models` response is a flat array (not wrapped in a `"data"` key),
 * so the dot-path used here is `"*.id"` rather than the `"data.*.id"` shape used
 * by OpenAI-compatible endpoints.
 */
class MistralAdapter extends AbstractProviderAdapter
{
    use CreatesMistralClient;

    /**
     * Creates a Mistral driver instance authenticated with the provider's API key.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Mistral,
            config: [
                'key' => $provider->api_key,
            ]
        );
    }

    /**
     * Fetches available Mistral models from the `/models` endpoint.
     *
     * The response is a flat JSON array; each element's `id` field is extracted
     * directly using the `"*.id"` dot-path.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->createModelListClient($this->client($provider->driver))
            ->get('/models')
            ->getMapped('*.id', function ($modelId) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: $modelId,
                    provider: $provider,
                );
            });
    }
}
