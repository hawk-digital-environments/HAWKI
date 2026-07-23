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
use Laravel\Ai\Gateway\OpenAi\Concerns\CreatesOpenAiClient;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Generic adapter for any third-party provider that exposes an OpenAI-compatible API.
 *
 * Use this as a base class when a provider speaks the OpenAI wire protocol but is not
 * one of the first-party integrations (OpenAI, Azure, OpenRouter, …). Subclasses can
 * pin a fixed base URL by setting {@see $baseUrl}, or leave it null to read the URL
 * from the provider's `api_url` database field at runtime.
 *
 * Example — a subclass that hard-codes the base URL:
 *
 * ```php
 * class MyCustomAdapter extends OpenAiLikeAdapter
 * {
 *     protected string|null $baseUrl = 'https://api.my-provider.com/v1';
 * }
 * ```
 *
 * When neither {@see $baseUrl} nor `api_url` is set, the driver is built with a null URL
 * and may fail at request time — subclasses that require an endpoint should override
 * {@see createDriver()} and call {@see assertNonEmptyApiUrl()} explicitly.
 */
class OpenAiLikeAdapter extends AbstractProviderAdapter
{
    use OpenAiModelListTrait;
    use CreatesOpenAiClient;

    /**
     * Optional hard-coded base URL for the provider's OpenAI-compatible endpoint.
     *
     * When set on a subclass, it takes precedence over the `api_url` stored in the
     * database, making the adapter self-contained without requiring operator configuration.
     */
    protected string|null $baseUrl = null;

    /**
     * Creates an OpenAI driver pointed at the resolved base URL.
     *
     * Prefers {@see $baseUrl} over the database `api_url` so that subclasses with a
     * fixed endpoint do not require the operator to configure a URL.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        $baseUri = $this->baseUrl ?? $provider->api_url;
        return $factory->make(
            driverName: Lab::OpenAI,
            config: [
                'key' => $provider->api_key,
                'url' => $baseUri
            ]
        );
    }

    /**
     * Fetches the model list from the provider's standard `/models` endpoint.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList($provider, $this->createModelListClient($this->client($provider->driver)));
    }
}
