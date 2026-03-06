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
use App\Services\AI\Db\AiModelSyncService;
use App\Services\AI\Db\ToolSyncService;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\StorageServiceFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
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

        // Register sync services as singletons so they can be resolved anywhere.
        $this->app->singleton(AiModelSyncService::class);
        $this->app->singleton(ToolSyncService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootWebdavStorage();
        $this->bootAiModelSync();
        $this->bootToolSync();
    }

    /**
     * On the very first artisan run (e.g. after fresh migrations) automatically
     * populate the ai_models / ai_providers tables from the config files so that
     * operators do not have to run `php artisan models:sync` manually.
     *
     * For normal web requests this is a cheap no-op (table already has rows).
     * It is guarded so that it never crashes a request even when the DB is not
     * yet available (e.g. during the initial `migrate` run itself).
     */
    protected function bootAiModelSync(): void
    {
        // Only auto-sync when running CLI commands, not on every web request.
        if (!$this->app->runningInConsole()) {
            return;
        }

        try {
            /** @var AiModelSyncService $syncService */
            $syncService = $this->app->make(AiModelSyncService::class);

            // Only sync when the table is empty (first install or after truncation).
            if (!$syncService->isSynced()) {
                $syncService->sync();
            }
        } catch (\Exception) {
            // Silently skip — DB or tables may not exist yet (e.g. during migrate).
        }
    }

    /**
     * On the very first artisan run, automatically populate ai_tools from config
     * so operators do not have to run `php artisan tools:sync` manually.
     *
     * Only function tools are auto-synced; MCP servers require network access and
     * must be synced explicitly via `php artisan tools:sync`.
     */
    protected function bootToolSync(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        try {
            /** @var ToolSyncService $syncService */
            $syncService = $this->app->make(ToolSyncService::class);

            if (!$syncService->isSynced()) {
                $syncService->syncFunctionTools();
            }
        } catch (\Exception) {
            // Silently skip — DB or tables may not exist yet (e.g. during migrate).
        }
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
                'baseUri'  => $config['base_uri'],
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
