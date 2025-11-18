<?php

namespace App\Providers;

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\DeprecatedEndpointMiddleware;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\ExternalCommunicationCheck;
use App\Http\Middleware\MandatorySignatureCheck;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\SessionExpiryChecker;
use App\Http\Middleware\TokenCreationCheck;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\StorageServiceFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Orchid\Support\Facades\Dashboard;
use Sabre\DAV\Client;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerMiddlewareAliases();
        $this->registerStorageServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootWebdavStorage();
        $this->configureOrchidUserModel();
        $this->loadDynamicConfiguration();
        $this->registerObservers();
    }

    /**
     * Load dynamic configuration from database
     */
    protected function loadDynamicConfiguration(): void
    {
        try {
            // Check if app_settings table exists and load settings
            if (\Schema::hasTable('app_settings')) {
                $settingsService = new \App\Services\SettingsService;
                $configSettings = $settingsService->getAllForConfig();

                foreach ($configSettings as $configKey => $value) {
                    // Set the configuration value - this OVERWRITES any existing config value
                    config([$configKey => $value]);
                }
            }
        } catch (\Exception $e) {
            // Silently fail during migrations or when database is not ready
        }
    }

    /**
     * Configure Orchid to use our custom User model
     */
    protected function configureOrchidUserModel(): void
    {
        Dashboard::useModel(
            \Orchid\Platform\Models\User::class,
            \App\Models\User::class
        );
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\AiModel::observe(\App\Observers\AiModelObserver::class);
    }

    protected function registerStorageServices(): void
    {
        $this->app->singleton(
            AvatarStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getAvatarStorage()
        );

        $this->app->singleton(
            FileStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getFileStorage()
        );
    }

    protected function bootWebdavStorage(): void
    {
        // Register WebDAV driver for NextCloud support
        Storage::extend('webdav', static function ($app, $config) {
            $client = new Client([
                'baseUri' => $config['base_uri'],
                'userName' => $config['username'],
                'password' => $config['password'],
            ]);

            $adapter = new WebDAVAdapter($client, $config['prefix'] ?? '');

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }

    private function registerMiddlewareAliases(): void
    {
        // Register middleware aliases
        Route::aliasMiddleware('registrationAccess', RegistrationAccess::class);
        Route::aliasMiddleware('roomAdmin', AdminAccess::class);
        Route::aliasMiddleware('roomEditor', EditorAccess::class);
        Route::aliasMiddleware('api_isActive', ExternalCommunicationCheck::class);
        Route::aliasMiddleware('prevent_back', PreventBackHistory::class);
        Route::aliasMiddleware('expiry_check', SessionExpiryChecker::class);
        Route::aliasMiddleware('token_creation', TokenCreationCheck::class);
        Route::aliasMiddleware('signature_check', MandatorySignatureCheck::class);
        Route::aliasMiddleware('deprecated', DeprecatedEndpointMiddleware::class);
    }
}
