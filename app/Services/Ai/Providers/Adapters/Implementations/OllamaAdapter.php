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
use Laravel\Ai\Gateway\Ollama\Concerns\CreatesOllamaClient;
use Laravel\Ai\Providers\Provider as Driver;

class OllamaAdapter extends AbstractProviderAdapter
{
    use CreatesOllamaClient;

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Ollama,
            config: [
                'url' => $provider->api_url,
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
        /* @see https://docs.ollama.com/api/ps */
        return $this->createModelListClient($provider)
            ->get('/ps')
            ->getMapped('models.*.model', function ($item) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: data_get($item, 'model'),
                    provider: $provider,
                );
            });
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, AiProviderProxy $provider): void
    {
        /* @see https://docs.ollama.com/api/ps */
        foreach ($this->createModelListClient($provider)->get('/ps')->getList('models.*.model') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
