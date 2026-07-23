<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;


use App\Models\Ai\AiProvider;
use App\Services\System\Container\ServiceLocator;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\AiManager;

/**
 * Singleton factory that creates per-provider {@see DriverFactory} instances.
 *
 * {@see DriverFactory} is a short-lived, provider-scoped object — it holds a reference to
 * one specific {@see AiProvider} and must not be shared across providers. This class owns
 * the shared dependencies ({@see AiManager} and {@see ServiceLocator}) as a singleton, and
 * hands them down into a fresh {@see DriverFactory} for each provider on demand.
 *
 * Typical usage inside a provider adapter:
 *
 * ```php
 * // Injected into a service that drives adapter resolution:
 * $factory = $driverFactoryFactory->createFactoryForProvider($aiProvider);
 * $driver  = $adapter->createDriver($aiProvider, $factory);
 * ```
 */
#[Singleton]
class DriverFactoryFactory
{
    public function __construct(
        private AiManager      $manager,
        private ServiceLocator $serviceLocator
    )
    {
    }

    /**
     * Returns a new {@see DriverFactory} scoped to the given provider.
     *
     * Each call produces a distinct instance so that concurrent resolution of
     * different providers cannot share configuration state.
     */
    public function createFactoryForProvider(AiProvider $provider): DriverFactory
    {
        return new DriverFactory($this->manager, $this->serviceLocator, $provider);
    }
}
