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
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\OpenAi\Concerns\CreatesOpenAiClient;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Provider adapter for OpenAI (api.openai.com).
 *
 * Uses {@see ExtendedOpenAiGateway} instead of the default gateway so that HAWKI's
 * custom gateway extensions are active (e.g. reasoning-token tracking). The gateway
 * is injected via the container builder closure so the event dispatcher is resolved
 * automatically.
 *
 * Exposes OpenAI's native web-search tool via {@see getNativeToolFactoryForCapability()},
 * causing HAWKI to delegate web-search requests to OpenAI's built-in implementation
 * rather than running its own HTTP tool.
 */
class OpenAiAdapter extends AbstractProviderAdapter
{
    use OpenAiModelListTrait;
    use CreatesOpenAiClient;

    /**
     * Creates an OpenAI driver using {@see ExtendedOpenAiGateway} so HAWKI's custom
     * gateway logic is applied to every request sent through this provider.
     */
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

    /**
     * Fetches the available OpenAI models from the standard `/models` endpoint.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList($provider, $this->createModelListClient($this->client($provider->driver)));
    }

    /**
     * Returns a factory for OpenAI's native web-search tool when the requested
     * capability is {@see WellKnownCapabilities::WEB_SEARCH}.
     *
     * Using the native tool means the model can call OpenAI's built-in search API
     * directly, which is more tightly integrated than routing through HAWKI's own
     * HTTP-fetch tool.
     *
     * @see https://developers.openai.com/api/docs/guides/tools-web-search
     */
    public function getNativeToolFactoryForCapability(string $capability): \Closure|null
    {
        return match ($capability) {
            WellKnownCapabilities::WEB_SEARCH => static fn() => new WebSearch(),
            default => null
        };
    }
}
