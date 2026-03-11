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
use App\Services\System\ScheduleWithDynamicIntervalFactory;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootWebdavStorage();
        $this->bootSchedulerMacros();
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

    private function bootSchedulerMacros(): void
    {
        Schedule::macro(
            'commandWithDynamicInterval',
            /**
             * Acts in the same way as the standard "command" method, but allows for dynamic scheduling intervals and arguments, which can be defined in the database or configuration.
             * This macro uses the {@see ScheduleWithDynamicIntervalFactory} to create the scheduled job, which handles the parsing and validation of the interval and arguments, and logs any errors that occur during scheduling.
             *
             * @param string $command The command to be scheduled.
             * @param array|null $parameters Optional parameters for the command.
             * @param mixed $interval The scheduling interval, which can be a string representing a scheduling method or the special "never" value.
             * @param mixed|null $intervalArgs Optional arguments for the scheduling method, which can be a JSON string, a single numeric value, or a simple string.
             * @return Event|null Returns the scheduled Event if successful, or null if there was an error in scheduling due to invalid interval or arguments.
             */
            function (
                string     $command,
                array|null $parameters = null,
                mixed      $interval = ScheduleWithDynamicIntervalFactory::NEVER_INTERVAL,
                mixed      $intervalArgs = null
            ): Event|null {
                return $this->app->make(ScheduleWithDynamicIntervalFactory::class)->makeJob(
                    command: $command,
                    parameters: $parameters,
                    interval: $interval,
                    intervalArgs: $intervalArgs
                );
            }
        );
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
