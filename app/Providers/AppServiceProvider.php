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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if(env('PHP_ERRORS') !== null){
            error_reporting(eval('return '.env('PHP_ERRORS').' ;'));
        }
    }
}
