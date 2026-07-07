<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\ProviderNotFoundException;
use App\Services\Ai\Providers\Adapters\DriverFactoryFactory;
use App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
readonly class AiProviderProxyResolver
{
    public function __construct(
        private AiProviderRepository    $providerRepository,
        private ProviderAdapterRegistry $adapterRegistry,
        private DriverFactoryFactory    $driverFactoryFactory
    )
    {
    }

    public function resolveForModel(AiModel $model): AiProviderProxy
    {
        return $this->resolve($model->provider);
    }

    public function resolve(string|int|AiProvider $provider): AiProviderProxy
    {
        $aiProvider = $this->resolveAiProvider($provider);
        $adapter = $this->adapterRegistry->getForProvider($aiProvider);
        $driverFactory = $this->driverFactoryFactory->createFactoryForProvider($aiProvider);
        $driver = $adapter->createDriver($aiProvider, $driverFactory);
        return new AiProviderProxy(
            provider: $aiProvider,
            adapter: $adapter,
            driver: $driver,
        );
    }

    private function resolveAiProvider(string|int|AiProvider $provider): AiProvider
    {
        if ($provider instanceof AiProvider) {
            return $provider;
        }

        $originalInput = $provider;
        if (is_string($provider)) {
            $provider = $this->providerRepository->findOneByProviderId($provider);
        } else {
            $provider = $this->providerRepository->findOne($provider);
        }

        if (!$provider instanceof AiProvider) {
            throw ProviderNotFoundException::forInput($originalInput);
        }

        return $provider;
    }
}
