<?php

namespace App\Providers;

use App\Http\Controllers\LanguageController;
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
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\DefaultStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\StorageServiceFactory;
use App\Services\SyncLog\Handlers\InvitationHandler;
use App\Services\SyncLog\Handlers\MemberHandler;
use App\Services\SyncLog\Handlers\MessageHandler;
use App\Services\SyncLog\Handlers\PrivateUserDataHandler;
use App\Services\SyncLog\Handlers\RoomAiWritingHandler;
use App\Services\SyncLog\Handlers\RoomHandler;
use App\Services\SyncLog\Handlers\UserHandler;
use App\Services\SyncLog\SyncLogTracker;
use App\Services\Translation\HawkiTranslationLoader;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\TranslationServiceProvider;
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
        // Register middleware aliases
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

        $this->app->singleton(
            DefaultStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getDefaultStorage()
        );

        $this->app->singleton(
            AvatarStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getAvatarStorage()
        );

        $this->app->singleton(
            FileStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getFileStorage()
        );
        
        $this->registerHawkiTranslationLoader();
        $this->registerSyncLogServices();
        $this->registerStorageServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootSyncLogTracker();
        $this->bootWebdavStorage();
    }
    
    /**
     * Injects a custom translation loader, which uses the LanguageController to fetch translations,
     * but still allows the use of the Laravel translation functions.
     */
    protected function registerHawkiTranslationLoader(): void
    {
        // We need to register the translation service provider, because it is marked as "deferred" (meaning it is not loaded at this point).
        // Calling it like this will ensure that the translation service provider is loaded before we can override it.
        $this->app->register(TranslationServiceProvider::class);
        // This is the main magic -> Replace the default translation loader with our custom one.
        $this->app->singleton('translation.loader', function (Application $app) {
            return new HawkiTranslationLoader(
                $app->make(LanguageController::class),
                $app->make('config')
            );
        });
        // To ensure the translator is instantiated with our custom loader, we tell the container to forget the current instance of the translator.
        // Next time the translator is requested, it will use our custom loader. 👽️
        $this->app->forgetInstance('translator');
    }
    
    protected function registerSyncLogServices(): void
    {
        $this->app->tag([
            RoomHandler::class,
            UserHandler::class,
            MemberHandler::class,
            InvitationHandler::class,
            PrivateUserDataHandler::class,
            MessageHandler::class,
            RoomAiWritingHandler::class,
        ], 'syncLog.handler');
    }
    
    protected function registerStorageServices(): void
    {
        $this->app->singleton(
            DefaultStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getDefaultStorage()
        );
        
        $this->app->singleton(
            AvatarStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getAvatarStorage()
        );
        
        $this->app->singleton(
            FileStorageService::class,
            fn(Application $app) => $app->make(StorageServiceFactory::class)->getFileStorage()
        );
    }
    
    protected function bootSyncLogTracker(): void
    {
        $this->app->get(SyncLogTracker::class)->registerListeners();
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
}
