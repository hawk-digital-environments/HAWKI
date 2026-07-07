<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Traits\OpenAiModelListTrait;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use Illuminate\Http\Client\PendingRequest;
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
                'version' => $this->findVersion($provider)
            ]
        );
    }

    public function createHttpClient(AiProviderProxy $provider): PendingRequest
    {
        return $this->client($provider->driver);
    }

    /**
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList(
            $provider,
            $this->createModelListClient($provider),
            $this->findEndpoint($provider->getRealProvider()) . '/openai/models'
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, AiProviderProxy $provider): void
    {
        /* @see https://learn.microsoft.com/en-us/rest/api/azureopenai/models/list?view=rest-azureopenai-2024-10-21&tabs=HTTP */
        $this->runOpenAiStatusCheck(
            $statusCollection,
            $this->createModelListClient($provider),
            $this->findEndpoint($provider->getRealProvider()) . '/openai/models'
        );
    }

    private function findVersion(AiProvider $provider): string
    {
        return $provider->additional_config['version'] ?? '2024-10-21';
    }

    private function findEndpoint(AiProvider $provider): string
    {
        return $this->assertNonEmptyApiUrl($provider->api_url, $provider);
    }
}
