<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\ExternalCommunicationCheck;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SessionExpiryChecker;
use Illuminate\Support\Facades\Route;
use Dotenv\Dotenv;




class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Route::aliasMiddleware('registrationAccess', RegistrationAccess::class);
        Route::aliasMiddleware('roomAdmin', AdminAccess::class);
        Route::aliasMiddleware('roomEditor', EditorAccess::class);
        Route::aliasMiddleware('api_isActive', ExternalCommunicationCheck::class);
        Route::aliasMiddleware('prevent_back', PreventBackHistory::class);
        Route::aliasMiddleware('expiry_check', SessionExpiryChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

    }
}
