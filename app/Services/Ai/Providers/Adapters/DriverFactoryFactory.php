<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;


use App\Models\Ai\AiProvider;
use App\Services\System\Container\ServiceLocator;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\AiManager;

#[Singleton]
class DriverFactoryFactory
{
    public function __construct(
        private AiManager      $manager,
        private ServiceLocator $serviceLocator
    )
    {
    }

    public function createFactoryForProvider(AiProvider $provider): DriverFactory
    {
        return new DriverFactory($this->manager, $this->serviceLocator, $provider);
    }
}
