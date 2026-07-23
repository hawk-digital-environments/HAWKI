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
use Laravel\Ai\Gateway\AzureOpenAi\Concerns\CreatesAzureOpenAiClient;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Provider adapter for Azure OpenAI.
 *
 * Configures the Laravel AI Azure driver using a user-supplied endpoint URL,
 * API key, and optional API version (defaults to `2024-10-21`).
 *
 * The API URL is mandatory — Azure OpenAI resources each have a unique hostname
 * tied to the Azure subscription, so there is no shared default to fall back on.
 * A missing URL triggers {@see \App\Services\Ai\Exceptions\InvalidProviderConfigurationException}.
 *
 * Model discovery uses the OpenAI-compatible `/openai/models` sub-path appended to
 * the configured endpoint URL, rather than the bare `/models` path used by the
 * standard OpenAI adapter.
 */
class AzureOpenAiAdapter extends AbstractProviderAdapter
{
    use OpenAiModelListTrait;
    use CreatesAzureOpenAiClient;

    /**
     * Creates an Azure OpenAI driver using the provider's endpoint, key, and API version.
     *
     * @throws \App\Services\Ai\Exceptions\InvalidProviderConfigurationException when `api_url` is empty.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Azure,
            config: [
                'url' => $this->findEndpoint($provider),
                'key' => $provider->api_key,
                'version' => $provider->additional_config['version'] ?? '2024-10-21'
            ]
        );
    }

    /**
     * Fetches the list of deployed models from the Azure OpenAI resource's `/openai/models` endpoint.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList(
            $provider,
            $this->createModelListClient($this->client($provider->driver)),
            $this->findEndpoint($provider->getRealProvider()) . '/openai/models'
        );
    }

    /**
     * Returns the validated endpoint URL, throwing when it is absent.
     *
     * Centralises the non-empty URL check so both {@see createDriver()} and
     * {@see getModels()} use the same validation path.
     *
     * @throws \App\Services\Ai\Exceptions\InvalidProviderConfigurationException when `api_url` is empty.
     */
    private function findEndpoint(AiProvider $provider): string
    {
        return $this->assertNonEmptyApiUrl($provider->api_url, $provider);
    }
}
