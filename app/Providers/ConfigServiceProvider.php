<?php

namespace App\Providers;

use App\Services\Config\AbstractConfig;
use App\Services\Config\ConfigService;
use App\Services\Config\Registries\PublicConfigRegistry;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PublicConfigRegistry::class, function ($app) {
            return new PublicConfigRegistry(new LazySingletonList(
                fn($class) => $class,
                fn($class) => $app->make(ConfigService::class)->get($class)
            ));
        });
    }

    public function boot(): void
    {
        $this->app->beforeResolving(
            AbstractConfig::class,
            function ($abstract) {
                if (!$this->app->bound($abstract)) {
                    $config = $this->app->make(ConfigService::class)->get($abstract);
                    $this->app->instance($abstract, $config);
                }
            }
        );
    }
}
