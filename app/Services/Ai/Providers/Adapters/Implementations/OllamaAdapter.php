<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Ollama\Concerns\CreatesOllamaClient;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Provider adapter for Ollama (self-hosted local models).
 *
 * Connects to a locally or privately hosted Ollama instance using the URL stored on
 * the provider record. No API key is required — Ollama runs without authentication
 * by default on its local endpoint.
 *
 * Model discovery uses the `/ps` ("currently running models") endpoint rather than
 * the `/tags` endpoint, so only models that are actively loaded in memory are returned.
 * This avoids surfacing models that are installed but not yet usable without a cold-start
 * delay.
 *
 * @see https://docs.ollama.com/api/ps Ollama running-models API
 */
class OllamaAdapter extends AbstractProviderAdapter
{
    use CreatesOllamaClient;

    /**
     * Creates an Ollama driver pointed at the provider's configured API URL.
     *
     * No API key is sent — Ollama's local endpoint is unauthenticated by default.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Ollama,
            config: [
                'url' => $provider->api_url,
            ]
        );
    }

    /**
     * Fetches the list of currently running models from the Ollama `/api/ps` endpoint.
     *
     * Only models that are actively loaded in Ollama's memory appear here. Models
     * installed but not running are excluded, which prevents returning models that
     * would require a cold-start before the first request.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     *
     * @see https://docs.ollama.com/api/ps
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->createModelListClient($this->client($provider->driver))
            ->get('/api/tags')
            ->getMapped('models.*', function ($item) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: data_get($item, 'model'),
                    provider: $provider,
                );
            });
    }

    /**
     * @inheritDoc
     */
    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider): void
    {
        foreach ($this->getModels($provider) as $model) {
            $statusCollection->setUnknown($model->model_id);
        }
        foreach ($this->createModelListClient($this->client($provider->driver))->get('/api/ps')->getList('models.*') as $model) {
            $statusCollection->setOnline(data_get($model, 'model'));
        }
    }
}
