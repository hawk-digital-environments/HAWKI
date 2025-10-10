<?php

namespace App\Providers;

use App\Services\Frontend\Connection\View\InternalFrontendConnectionComponent;
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
    }
}
