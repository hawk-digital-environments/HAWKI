<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
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

    /**
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        /* @see https://docs.ollama.com/api/ps */
        return $this->createModelListClient($this->client($provider->driver))
            ->get('/ps')
            ->getMapped('models.*.model', function ($item) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: data_get($item, 'model'),
                    provider: $provider,
                );
            });
    }
}
