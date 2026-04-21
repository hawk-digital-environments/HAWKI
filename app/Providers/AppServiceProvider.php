<?php

namespace App\Providers;

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\AppAccessMiddleware;
use App\Http\Middleware\AppUserRequestRequiredMiddleware;
use App\Http\Middleware\DeprecatedEndpointMiddleware;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\ExternalAccessMiddleware;
use App\Http\Middleware\HandleAppConnectMiddleware;
use App\Http\Middleware\MandatorySignatureCheck;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\SessionExpiryChecker;
use App\Http\Middleware\TokenCreationCheck;
use App\Services\System\ScheduleWithDynamicIntervalFactory;
use App\Utils\Arrays\RecursiveMergeOption;
use App\Utils\Arrays\RecursiveMerger;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerMiddlewareAliases();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootSchedulerMacros();
        $this->bootArrMacros();
    }

    /** @noinspection StaticClosureCanBeUsedInspection */
    private function bootSchedulerMacros(): void
    {
        $app = $this->app;
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
            ) use ($app): Event|null {
                return $app->make(ScheduleWithDynamicIntervalFactory::class)->makeJob(
                    command: $command,
                    parameters: $parameters,
                    interval: $interval,
                    intervalArgs: $intervalArgs
                );
            }
        );
    }

    private function bootArrMacros(): void
    {
        Arr::macro(
            'mergeRecursive',
            /**
             * This method merges multiple arrays into each other. It will traverse elements recursively. While
             * traversing the second array ($b) all its values will be merged into the first array ($a). The values of $b will
             * overrule the values in $a. If both values are arrays the merge will go deeper and merge the child arrays into
             * each other.
             *
             * NOTE: By default numeric keys will be merged into each other so: [["foo"]] + [["bar"]] becomes [["bar"]].
             * This however is only the case for ARRAYS! All other values will be appended to $a, so ["a"] + ["b"] becomes
             * ["a", "b"]. You can use the {@see RecursiveMergeOption::STRICT_NUMERIC_MERGE} and {@see RecursiveMergeOption::NO_NUMERIC_MERGE} flags to control the behavior directly.
             *
             * NOTE2: It is possible to remove keys from an array while they are merge by using the __UNSET special value.
             * Keep in mind, that the {@see RecursiveMergeOption::ALLOW_REMOVAL} flag has to be enabled for that.
             */
            function (array $a, array $b, array|RecursiveMergeOption ...$args) {
                return RecursiveMerger::merge($a, $b, ...$args);
            });
    }

    private function registerMiddlewareAliases(): void
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
}
