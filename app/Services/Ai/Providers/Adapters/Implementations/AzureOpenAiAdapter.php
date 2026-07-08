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

class AzureOpenAiAdapter extends AbstractProviderAdapter
{
    use OpenAiModelListTrait;
    use CreatesAzureOpenAiClient;

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
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList(
            $provider,
            $this->createModelListClient($this->client($provider->driver)),
            $this->findEndpoint($provider->getRealProvider()) . '/openai/models'
        );
    }

    private function findEndpoint(AiProvider $provider): string
    {
        return $this->assertNonEmptyApiUrl($provider->api_url, $provider);
    }
}
