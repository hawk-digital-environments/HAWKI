<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;


use App\Models\Ai\AiProvider;
use App\Services\Ai\LaravelAi\ExtendedAiManager;
use App\Services\System\Container\ServiceLocator;
use Laravel\Ai\AiManager;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Provider as Driver;

readonly class DriverFactory
{
    public function __construct(
        private AiManager      $manager,
        private ServiceLocator $serviceLocator,
        private AiProvider     $provider
    )
    {
    }

    public function make(
        Lab|string    $driverName,
        array         $config,
        \Closure|null $builder = null
    ): Driver
    {
        $driverName = $driverName instanceof Lab ? $driverName->value : $driverName;

        $manager = $this->manager;
        if (!$manager instanceof ExtendedAiManager) {
            // @todo exception
            throw new \RuntimeException('AiManager is not an instance of ExtendedAiManager');
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
