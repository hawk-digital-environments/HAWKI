<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Anthropic\Concerns\CreatesAnthropicClient;
use Laravel\Ai\Providers\Provider as Driver;


class AnthropicAdapter extends AbstractProviderAdapter
{
    use CreatesAnthropicClient;

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Anthropic,
            config: [
                'key' => $provider->api_key,
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
        /* @see https://platform.claude.com/docs/en/api/models/list */
        return $this->createModelListClient($provider)
            ->get('/models')
            ->getMapped('data.*', function ($item) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: data_get($item, 'id'),
                    provider: $provider,
                );
            });
    }

    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void
    {
        /* @see https://platform.claude.com/docs/en/api/models/list */
        foreach ($this->createModelListClient($provider)->get('/models')->getList('data.*.id') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
