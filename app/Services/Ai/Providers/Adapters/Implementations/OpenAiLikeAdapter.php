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


    public function createHttpClient(AiProviderProxy $provider): PendingRequest
    {
        return $this->client($provider->driver);
    }

    /**
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList($provider, $this->createModelListClient($provider));
    }

    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void
    {
        $this->runOpenAiStatusCheck($statusCollection, $this->createModelListClient($provider));
    }
}
