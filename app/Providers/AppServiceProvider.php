<?php

namespace App\Providers;

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\AppAccessMiddleware;
use App\Http\Middleware\AppUserRequestRequiredMiddleware;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\ExternalAccessMiddleware;
use App\Http\Middleware\HandleAppConnectMiddleware;
use App\Http\Middleware\MandatorySignatureCheck;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\SessionExpiryChecker;
use App\Http\Middleware\TokenCreationCheck;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\DeprecatedEndpointMiddleware;


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
        Route::aliasMiddleware('prevent_back', PreventBackHistory::class);
        Route::aliasMiddleware('expiry_check', SessionExpiryChecker::class);
        Route::aliasMiddleware('token_creation', TokenCreationCheck::class);
        Route::aliasMiddleware('external_access', ExternalAccessMiddleware::class);
        Route::aliasMiddleware('app_access', AppAccessMiddleware::class);
        Route::aliasMiddleware('handle_app_connect', HandleAppConnectMiddleware::class);
        Route::aliasMiddleware('app_user_request_required', AppUserRequestRequiredMiddleware::class);
        Route::aliasMiddleware('signature_check', MandatorySignatureCheck::class);
        Route::aliasMiddleware('deprecated', DeprecatedEndpointMiddleware::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
