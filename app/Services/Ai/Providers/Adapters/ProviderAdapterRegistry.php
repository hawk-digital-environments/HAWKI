<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Providers\AiServiceProvider;
use App\Services\Ai\Exceptions\ProviderAdapterAlreadyRegisteredException;
use App\Services\Ai\Exceptions\ProviderAdapterNotFoundException;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central registry that maps adapter keys to {@see ProviderAdapterInterface} implementations.
 *
 * Adapter keys are arbitrary strings (see {@see WellKnownAdapterKeys} for built-in ones).
 * Each key maps to a class name; instances are created lazily by the container through the
 * shared {@see LazySingletonList} and reused across calls for the same key.
 *
 * Built-in adapters are registered in {@see AiServiceProvider} via repeated {@see declare()} calls.
 * Third-party packages may extend the registry through the same mechanism to add their own providers.
 *
 * Usage example (in a service provider):
 *
 * ```php
 * $this->app->extend(
 *     ProviderAdapterRegistry::class,
 *     fn(ProviderAdapterRegistry $registry) => $registry
 *         ->declare(WellKnownAdapterKeys::OPENAI, OpenAiAdapter::class)
 *         ->declare('my_custom_provider', MyCustomAdapter::class),
 * );
 * ```
 *
 * @see AiServiceProvider for the built-in adapter registrations.
 * @api
 */
#[Singleton]
class ProviderAdapterRegistry
{
    private array $adapterClasses = [];

    public function __construct(
        /**
         * Lazy singleton cache keyed by [adapterKey, adapterClass] tuples.
         * Adapter instances are resolved through the container on first access and reused thereafter.
         *
         * @var LazySingletonList<array{0: string, 1: class-string<ProviderAdapterInterface>}, ProviderAdapterInterface>
         */
        #[Give(AiServiceProvider::PROVIDER_ADAPTER_LIST)]
        private readonly LazySingletonList $instances
    )
    {
    }

    /**
     * Returns true if an adapter class has been declared under the given key.
     */
    public function has(string $key): bool
    {
        return $this->instances->has($this->resolveInstancesKey($key)) || isset($this->adapterClasses[$key]);
    }

    /**
     * Registers an adapter class under the given key.
     *
     * The adapter is resolved from the container lazily on the first {@see get()} call for
     * this key, so declaring an adapter does not trigger instantiation.
     *
     * @param string $key Unique adapter identifier (e.g. `'openai'`).
     * @param class-string<ProviderAdapterInterface> $adapterClass Fully-qualified class name that implements {@see ProviderAdapterInterface}.
     * @return $this For fluent chaining of multiple declarations.
     *
     * @throws \App\Services\Ai\Exceptions\ProviderAdapterAlreadyRegisteredException when a different class is already registered under $key.
     * @throws \InvalidArgumentException when $adapterClass does not exist or does not implement {@see ProviderAdapterInterface}.
     */
    public function declare(string $key, string $adapterClass): self
    {
        if (isset($this->adapterClasses[$key])) {
            throw ProviderAdapterAlreadyRegisteredException::forKey($key);
        }

        if (!class_exists($adapterClass)) {
            // @todo exception
            throw new \InvalidArgumentException(sprintf('Adapter class "%s" does not exist.', $adapterClass));
        }

        if (!is_subclass_of($adapterClass, ProviderAdapterInterface::class)) {
            // @todo exception
            throw new \InvalidArgumentException(sprintf('Adapter class "%s" must implement %s.', $adapterClass, ProviderAdapterInterface::class));
        }

        $this->adapterClasses[$key] = $adapterClass;

        return $this;
    }

    /**
     * Returns the adapter instance registered under the given key.
     *
     * The instance is created by the container on first access and cached for subsequent calls.
     *
     * @throws \App\Services\Ai\Exceptions\ProviderAdapterNotFoundException when no adapter is registered under $key.
     */
    public function get(string $key): ProviderAdapterInterface
    {
        if (!isset($this->adapterClasses[$key])) {
            throw ProviderAdapterNotFoundException::forKey($key);
        }

        return $this->instances->get($this->resolveInstancesKey($key));
    }

    /**
     * Returns the adapter for the given provider, using its `adapter_key` attribute for lookup.
     *
     * @throws \App\Services\Ai\Exceptions\ProviderAdapterNotFoundException when the provider's adapter_key is not registered.
     */
    public function getForProvider(AiProvider $provider): ProviderAdapterInterface
    {
        return $this->get($provider->adapter_key);
    }

    /**
     * Returns the adapter for the provider that owns the given model.
     *
     * @throws \App\Services\Ai\Exceptions\ProviderAdapterNotFoundException when the model's provider adapter_key is not registered.
     */
    public function getForModel(AiModel $model): ProviderAdapterInterface
    {
        return $this->getForProvider($model->provider);
    }

    /**
     * Builds the composite key used by the {@see LazySingletonList} for a given adapter key.
     *
     * Returns null when the key is not declared, which causes {@see LazySingletonList::has()}
     * to return false rather than throwing.
     *
     * @return array{0: string, 1: class-string<ProviderAdapterInterface>}|null
     */
    private function resolveInstancesKey(string $key): array|null
    {
        if (isset($this->adapterClasses[$key])) {
            return [$key, $this->adapterClasses[$key]];
        }
        return null;
    }

    /**
     * Removes the adapter registered under the given key from the instance cache.
     *
     * Does nothing when the key is not registered. Primarily intended for testing — removing a
     * key forces the next {@see get()} call to re-resolve the adapter from the container.
     */
    public function remove(string $key): void
    {
        $this->instances->remove($this->resolveInstancesKey($key));
        unset($this->adapterClasses[$key]);
    }

}
