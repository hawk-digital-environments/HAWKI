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

/**
 * Resolves a fully-wired {@see AiProviderProxy} from a provider identifier.
 *
 * This is the single entry point for obtaining a provider proxy in application code.
 * It looks up the {@see AiProvider} Eloquent model (when a string or integer ID is given),
 * selects the correct {@see ProviderAdapterInterface} implementation from the registry,
 * creates the underlying AI driver via the adapter, and bundles everything into an
 * {@see AiProviderProxy} that is ready to use.
 *
 * Usage:
 * ```php
 * // Resolve by string provider_id (config key):
 * $proxy = $resolver->resolve('openAi');
 *
 * // Resolve by database primary key:
 * $proxy = $resolver->resolve(42);
 *
 * // Resolve by an already-loaded Eloquent model (skips the DB lookup):
 * $proxy = $resolver->resolve($aiProvider);
 *
 * // Convenience shortcut when you already have an AiModel:
 * $proxy = $resolver->resolveForModel($aiModel);
 * ```
 *
 * @throws ProviderNotFoundException when no provider record matches the given identifier.
 */
#[Singleton]
class AiProviderProxyResolver
{
    public function __construct(
        private readonly AiProviderRepository    $providerRepository,
        private readonly ProviderAdapterRegistry $adapterRegistry,
        private readonly DriverFactoryFactory    $driverFactoryFactory
    )
    {
    }

    /**
     * Convenience wrapper that resolves the proxy for the provider that owns the given model.
     *
     * Equivalent to `$resolver->resolve($model->provider)` but avoids the caller having to
     * access the `provider` relation directly.
     */
    public function resolveForModel(AiModel $model): AiProviderProxy
    {
        return $this->resolve($model->provider);
    }

    /**
     * Resolves a provider proxy from a string provider_id, an integer primary key, or an
     * already-loaded {@see AiProvider} model.
     *
     * When a model instance is passed the database lookup is skipped entirely, making this
     * safe to call inside loops that have already eager-loaded the provider relation.
     *
     * @throws ProviderNotFoundException when the identifier does not match any stored provider.
     */
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

    /**
     * Normalises the polymorphic `$provider` argument to a concrete {@see AiProvider} model.
     *
     * String inputs are matched against the `provider_id` column (the config key).
     * Integer inputs are matched against the primary key.
     * Model instances are returned as-is.
     *
     * @throws ProviderNotFoundException when a database lookup yields no result.
     */
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
