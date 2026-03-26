<?php

namespace App\Providers;

use App\Services\Frontend\Connection\View\InternalFrontendConnectionComponent;
use App\Services\Frontend\Connection\View\InternalFrontendLoginConnectionComponent;
use App\Services\Frontend\View\SettingsPanelComponent;
use Blade;
use Illuminate\Support\ServiceProvider;

class FrontendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Blade::component('internal-frontend-connection', InternalFrontendConnectionComponent::class);
        Blade::component('internal-frontend-connection-login', InternalFrontendLoginConnectionComponent::class);
        Blade::component('settings-panel', SettingsPanelComponent::class);
    }
}
