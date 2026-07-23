<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\InvalidAiManagerException;
use App\Services\Ai\LaravelAi\ExtendedAiManager;
use App\Services\System\Container\ServiceLocator;
use Laravel\Ai\AiManager;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Builds a framework-level Laravel AI driver instance for a specific {@see AiProvider}.
 *
 * Created per-provider by {@see DriverFactoryFactory::createFactoryForProvider()} and passed
 * into {@see \App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface::createDriver()}
 * so that adapters do not need to know about config merging or container resolution.
 *
 * The assembled configuration for every driver follows this precedence (last wins):
 *  1. HAWKI defaults: `name`, `driver`, `store = false`.
 *  2. Provider-level adapter settings from {@see \App\Services\Ai\Providers\Values\ProviderSettings::getAdapterSettings()}.
 *  3. Adapter-supplied `$config` (e.g. `api_key`, `url`).
 *
 * When a `$builder` closure is supplied the driver is constructed via the container so that
 * framework services (e.g. `Dispatcher`) can be injected automatically.  Without a builder,
 * `ExtendedAiManager::instanceWithConfig()` is called directly.
 */
class DriverFactory
{
    public function __construct(
        private readonly AiManager      $manager,
        private readonly ServiceLocator $serviceLocator,
        private readonly AiProvider     $provider
    )
    {
    }

    /**
     * Assembles the merged configuration and instantiates the requested driver.
     *
     * @param Lab|string   $driverName  Framework driver name (a {@see Lab} enum value or its string equivalent).
     * @param array        $config      Adapter-specific config entries that are merged on top of the provider defaults.
     * @param \Closure|null $builder    Optional factory closure resolved through the container. Receives the merged
     *                                  `$config` array and the current {@see AiProvider} as named parameters, plus
     *                                  any additional dependencies resolved by the container.
     *
     * @throws \App\Services\Ai\Exceptions\InvalidAiManagerException when the bound {@see AiManager} is not the
     *                                                                 HAWKI-extended variant.
     */
    public function make(
        Lab|string    $driverName,
        array         $config,
        \Closure|null $builder = null
    ): Driver
    {
        $driverName = $driverName instanceof Lab ? $driverName->value : $driverName;

        $manager = $this->manager;
        if (!$manager instanceof ExtendedAiManager) {
            throw InvalidAiManagerException::forNotExtendedManager();
        }

        $fullConfig = array_merge(
            [
                'name' => 'hawki_' . $this->provider->provider_id,
                'driver' => $driverName,
                'store' => false
            ],
            $this->provider->settings->getAdapterSettings(),
            $config,
        );

        if ($builder) {
            return $this->serviceLocator->call(
                ['builder', $driverName],
                $builder,
                [
                    'config' => $fullConfig,
                    'provider' => $this->provider,
                ]);
        }

        return $manager->instanceWithConfig($driverName, $fullConfig);
    }
}
