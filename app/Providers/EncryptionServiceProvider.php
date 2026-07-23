<?php

namespace App\Providers;

use App\Services\Config\Registries\PublicConfigRegistry;
use App\Services\Encryption\Config\SaltConfig;
use Illuminate\Support\ServiceProvider;

class EncryptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend(
            PublicConfigRegistry::class,
            fn(PublicConfigRegistry $registry) => $registry->declare(SaltConfig::class)
        );
    }

    public function boot(): void
    {
    }
}
