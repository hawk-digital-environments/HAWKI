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
use Laravel\Ai\Gateway\Mistral\Concerns\CreatesMistralClient;
use Laravel\Ai\Providers\Provider as Driver;

class MistralAdapter extends AbstractProviderAdapter
{
    use CreatesMistralClient;

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Mistral,
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
        return $this->createModelListClient($provider)
            ->get('/models')
            ->getMapped('*.id', function ($modelId) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: $modelId,
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
        /* @see https://docs.mistral.ai/api/endpoint/models#operation-list_models_v1_models_get */
        foreach ($this->createModelListClient($provider)->get('/models')->getList('*.id') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
