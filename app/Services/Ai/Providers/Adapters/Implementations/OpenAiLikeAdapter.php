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

class OpenAiLikeAdapter extends AbstractProviderAdapter
{
    use OpenAiModelListTrait;
    use CreatesOpenAiClient;

    protected string|null $baseUrl = null;

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
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList($provider, $this->createModelListClient($this->client($provider->driver)));
    }
}
