<?php

namespace App\Providers;

use App\Services\Config\Registries\PublicConfigRegistry;
use App\Services\ExtApp\Config\ExtAppConfig;
use Illuminate\Support\ServiceProvider;

class ExtAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend(
            PublicConfigRegistry::class,
            fn(PublicConfigRegistry $registry) => $registry->declare(ExtAppConfig::class)
        );
    }

    public function boot(): void
    {
    }
}
