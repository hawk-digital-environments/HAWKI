<?php

namespace App\Providers;

use App\Services\Config\Registries\PublicConfigRegistry;
use App\Services\Frontend\Config\SecurityConfig;
use App\Services\Frontend\Config\TransferConfig;
use App\Services\Frontend\View\CssLayers;
use App\Services\Frontend\View\EarlyFrontendBridge;
use App\Services\Frontend\View\SettingsPanelComponent;
use App\Services\Frontend\View\SvelteComponent;
use Blade;
use Illuminate\Support\ServiceProvider;

class FrontendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend(
            PublicConfigRegistry::class,
            function (PublicConfigRegistry $registry) {
                return $registry
                    ->declare(TransferConfig::class)
                    ->declare(SecurityConfig::class);
            }
        );
    }

    public function boot(): void
    {
        Blade::component('svelte', SvelteComponent::class);
        Blade::component('css-layers', CssLayers::class);
        Blade::component('early-frontend-bridge', EarlyFrontendBridge::class);
        Blade::component('settings-panel', SettingsPanelComponent::class);
    }
}
