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
 * @see AiServiceProvider for the built-in adapter registrations.
 * @api
 */
#[Singleton]
class ProviderAdapterRegistry
{
    private array $adapterClasses = [];

    public function __construct(
        /**
         * @var LazySingletonList<array, ProviderAdapterInterface>
         */
        #[Give(AiServiceProvider::PROVIDER_ADAPTER_LIST)]
        private readonly LazySingletonList $instances
    )
    {
    }

    /** Returns true if an adapter is registered under `$key`. */
    public function has(string $key): bool
    {
        return $this->instances->has($this->resolveInstancesKey($key));
    }

    /**
     * @param string $key
     * @param class-string<ProviderAdapterInterface> $adapterClass
     */
    public function declare(string $key, string $adapterClass): self
    {
        if (isset($this->adapterClasses[$key])) {
            throw ProviderAdapterAlreadyRegisteredException::forKey($key);
        }

        if (!class_exists($adapterClass)) {
            throw new \InvalidArgumentException(sprintf('Adapter class "%s" does not exist.', $adapterClass));
        }

        if (!is_subclass_of($adapterClass, ProviderAdapterInterface::class)) {
            throw new \InvalidArgumentException(sprintf('Adapter class "%s" must implement %s.', $adapterClass, ProviderAdapterInterface::class));
        }

        $this->adapterClasses[$key] = $adapterClass;

        return $this;
    }

    /**
     * Returns the builder for `$key`.
     *
     * @throws ProviderAdapterNotFoundException if no adapter is registered under `$key`.
     */
    public function get(string $key): ProviderAdapterInterface
    {
        if (!isset($this->adapterClasses[$key])) {
            throw ProviderAdapterNotFoundException::forKey($key);
        }

        return $this->instances->get($this->resolveInstancesKey($key));
    }

    public function getForProvider(AiProvider $provider): ProviderAdapterInterface
    {
        return $this->get($provider->adapter_key);
    }

    public function getForModel(AiModel $model): ProviderAdapterInterface
    {
        return $this->getForProvider($model->provider);
    }

    /** Removes the adapter registered under `$key`. Does nothing if the key is absent. */
    public function remove(string $key): void
    {
        $this->instances->remove($this->resolveInstancesKey($key));
    }

    private function resolveInstancesKey(string $key): array|null
    {
        if (isset($this->adapterClasses[$key])) {
            return [$key, $this->adapterClasses[$key]];
        }
        return null;
    }

}
