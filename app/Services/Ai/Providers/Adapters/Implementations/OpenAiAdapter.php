<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\LaravelAi\Drivers\OpenAi\ExtendedOpenAiGateway;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Traits\OpenAiModelListTrait;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\OpenAi\Concerns\CreatesOpenAiClient;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\WebSearch;

class OpenAiAdapter extends AbstractProviderAdapter
{
    use OpenAiModelListTrait;
    use CreatesOpenAiClient;

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::OpenAI,
            config: [
                'key' => $provider->api_key,
            ],
            builder: function (Dispatcher $dispatcher, array $config) {
                return new OpenAiProvider(
                    gateway: new ExtendedOpenAiGateway($dispatcher),
                    config: $config,
                    events: $dispatcher
                );
            }
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

    public function getNativeToolFactoryForCapability(string $capability): \Closure|null
    {
        return match ($capability) {
            /* @see https://developers.openai.com/api/docs/guides/tools-web-search */
            WellKnownCapabilities::WEB_SEARCH => static fn() => new WebSearch(),
            default => null
        };
    }
}
