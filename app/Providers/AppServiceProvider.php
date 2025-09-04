<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\ExternalCommunicationCheck;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SessionExpiryChecker;
use App\Http\Middleware\TokenCreationCheck;
use Illuminate\Support\Facades\Route;
use Dotenv\Dotenv;

use App\Services\AI\AIProviderFactory;
use App\Services\AI\AIConnectionService;
use App\Services\ProviderSettingsService;
use App\Services\Citations\CitationService;
use App\Providers\ConfigServiceProvider;
use App\Models\User;
use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use App\Models\ApiFormat;
use App\Models\ApiFormatEndpoint;
use App\Observers\UserObserver;
use App\Observers\LanguageModelObserver;
use App\Observers\ProviderSettingObserver;
use App\Observers\ApiFormatObserver;
use App\Observers\ApiFormatEndpointObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register middleware aliases
        Route::aliasMiddleware('registrationAccess', RegistrationAccess::class);
        Route::aliasMiddleware('roomAdmin', AdminAccess::class);
        Route::aliasMiddleware('roomEditor', EditorAccess::class);
        Route::aliasMiddleware('api_isActive', ExternalCommunicationCheck::class);
        Route::aliasMiddleware('prevent_back', PreventBackHistory::class);
        Route::aliasMiddleware('expiry_check', SessionExpiryChecker::class);
        Route::aliasMiddleware('token_creation', TokenCreationCheck::class);
        
        // Register AI services
        $this->app->singleton(AIProviderFactory::class, function ($app) {
            return new AIProviderFactory();
        });
        
        $this->app->singleton(AIConnectionService::class, function ($app) {
            return new AIConnectionService(
                $app->make(AIProviderFactory::class)
            );
        });

        $this->app->singleton(ProviderSettingsService::class, function ($app) {
            return new ProviderSettingsService();
        });

        // Register Citation Service
        $this->app->singleton(CitationService::class, function ($app) {
            return new CitationService();
        });

        // Register the ConfigServiceProvider
        $this->app->register(ConfigServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register User Observer for automatic role synchronization
        User::observe(UserObserver::class);
        
        // Register AI-related observers for cache invalidation
        LanguageModel::observe(LanguageModelObserver::class);
        ProviderSetting::observe(ProviderSettingObserver::class);
        ApiFormat::observe(ApiFormatObserver::class);
        ApiFormatEndpoint::observe(ApiFormatEndpointObserver::class);
    }
}
