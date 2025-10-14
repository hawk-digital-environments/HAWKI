<?php

namespace App\Providers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\ExternalCommunicationCheck;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SessionExpiryChecker;
use App\Http\Middleware\TokenCreationCheck;
use App\Http\Middleware\MandatorySignatureCheck;
use App\Services\Storage\StorageServiceFactory;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Foundation\Application;
use League\Flysystem\Filesystem;
use League\Flysystem\WebDAV\WebDAVAdapter;
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
    }
}
