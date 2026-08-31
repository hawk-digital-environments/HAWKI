<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\DeprecatedEndpointMiddleware;
use App\Http\Middleware\EditorAccess;
use App\Http\Middleware\MandatorySignatureCheck;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RegistrationAccess;
use App\Http\Middleware\SessionExpiryChecker;
use App\Http\Middleware\TokenCreationCheck;
use App\JsonApi\V1\Server;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantFeedback;
use App\Observers\AssistantFeedbackObserver;
use App\Observers\AssistantObserver;
use App\Services\Ai\Streaming\AgentStreamer;
use App\Services\Ai\Streaming\AgentStreamerInterface;
use App\Services\System\Http\SsrfSafeGetterMacro;
use App\Services\System\ScheduleWithDynamicIntervalFactory;
use App\Services\System\Time\CarbonClock;
use App\Services\System\Time\CarbonClockInterface;
use App\Services\System\Time\TimezoneGuard;
use App\Services\System\UsageTypes\UsageContext;
use App\Services\System\UserTypes\UserContext;
use App\Services\Translation\LocaleService;
use App\Utils\Arrays\RecursiveMergeOption;
use App\Utils\Arrays\RecursiveMerger;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use LaravelJsonApi\Core\Support\AppResolver;
use Psr\Clock\ClockInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerMiddlewareAliases();
        $this->registerDisablingGlobalScopesForEloquentUserProvider();
        $this->registerClockForInterface();
        $this->registerJsonApiServer();
        $this->registerAssistantServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TimezoneGuard::ensureSafe((string) config('app.timezone'));

        $this->bootSchedulerMacros();
        $this->bootArrMacros();
        $this->bootUrlGeneratorMacros();
        $this->bootRequestMacros();
        $this->bootHttpMacros();
        $this->bootObservers();
    }

    private function registerJsonApiServer(): void
    {
        $this->app->singleton(Server::class, static fn (Application $app) => new Server(
            new AppResolver(static fn () => $app),
            'v1',
        ));
    }

    private function registerAssistantServices(): void
    {
        $this->app->singleton(AgentStreamerInterface::class, AgentStreamer::class);
    }

    private function bootObservers(): void
    {
        Assistant::observe(AssistantObserver::class);
        AssistantFeedback::observe(AssistantFeedbackObserver::class);
    }

    /**
     * @noinspection StaticClosureCanBeUsedInspection
     */
    private function bootSchedulerMacros(): void
    {
        $app = $this->app;
        Schedule::macro(
            'commandWithDynamicInterval',
            /**
             * Acts in the same way as the standard "command" method, but allows for dynamic scheduling intervals and arguments, which can be defined in the database or configuration.
             * This macro uses the {@see ScheduleWithDynamicIntervalFactory} to create the scheduled job, which handles the parsing and validation of the interval and arguments, and logs any errors that occur during scheduling.
             *
             * @param string     $command      the command to be scheduled
             * @param null|array $parameters   optional parameters for the command
             * @param mixed      $interval     the scheduling interval, which can be a string representing a scheduling method or the special "never" value
             * @param null|mixed $intervalArgs optional arguments for the scheduling method, which can be a JSON string, a single numeric value, or a simple string
             *
             * @return null|Event returns the scheduled Event if successful, or null if there was an error in scheduling due to invalid interval or arguments
             */
            function (
                string $command,
                ?array $parameters = null,
                mixed $interval = ScheduleWithDynamicIntervalFactory::NEVER_INTERVAL,
                mixed $intervalArgs = null,
            ) use ($app): Event|null {
                return $app->make(ScheduleWithDynamicIntervalFactory::class)->makeJob(
                    command: $command,
                    parameters: $parameters,
                    interval: $interval,
                    intervalArgs: $intervalArgs,
                );
            },
        );
    }

    /**
     * @noinspection StaticClosureCanBeUsedInspection
     */
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
            },
        );
    }

    private function bootUrlGeneratorMacros(): void
    {
        UrlGenerator::macro(
            'getForcedRoot',
            function () {
                return $this->forcedRoot;
            },
        );
    }

    private function bootRequestMacros(): void
    {
        $app = $this->app;
        Request::macro(
            'getUsageContext',
            /**
             * Retrieves the UsageContext instance for this request.
             */
            function () use ($app): UsageContext {
                return $app->get(UsageContext::class);
            },
        );
        Request::macro(
            'getUserContext',
            /**
             * Retrieves the UserContext instance for this request.
             */
            function () use ($app): UserContext {
                return $app->get(UserContext::class);
            },
        );
        Request::macro(
            'getLocaleContext',
            /**
             * Retrieves the LocaleService instance for this request, which provides information about the user's locale and language preferences.
             */
            function () use ($app): LocaleService {
                return $app->get(LocaleService::class);
            },
        );
    }

    private function bootHttpMacros(): void
    {
        PendingRequest::macro(
            'getSsrfSafe',
            /**
             * A wrapper around the standard "get" method that performs additional checks to prevent SSRF vulnerabilities.
             * This macro uses the {@see SsrfSafeGetterMacro} to execute the GET request, which validates the URL and query parameters against a whitelist and logs any blocked attempts.
             *
             * @param string            $url   the URL to send the GET request to
             * @param null|array|string $query optional query parameters for the GET request, which can be an array, a JSON string, or a simple string
             *
             * @throws ConnectionException  if there was an error connecting to the URL
             * @throws SsrfBlockedException if the URL or query parameters were blocked by the SSRF protection mechanism
             *
             * @return Response Returns the HTTP response from the GET request if successful
             */
            function (
                string $url,
                null|array|string $query = null,
            ): Response {
                return SsrfSafeGetterMacro::execute($this, $url, $query);
            },
        );
    }

    private function registerMiddlewareAliases(): void
    {
        Route::aliasMiddleware('registrationAccess', RegistrationAccess::class);
        Route::aliasMiddleware('roomAdmin', AdminAccess::class);
        Route::aliasMiddleware('roomEditor', EditorAccess::class);
        Route::aliasMiddleware('prevent_back', PreventBackHistory::class);
        Route::aliasMiddleware('expiry_check', SessionExpiryChecker::class);
        Route::aliasMiddleware('token_creation', TokenCreationCheck::class);
        Route::aliasMiddleware('signature_check', MandatorySignatureCheck::class);
        Route::aliasMiddleware('deprecated', DeprecatedEndpointMiddleware::class);
    }

    private function registerDisablingGlobalScopesForEloquentUserProvider(): void
    {
        // Because our User model uses the KnownUsersAccessScope, which relies on the request declaring a user,
        // we create an infinite loop when Laravel tries to find the user.
        // To avoid this, we reconfigure the "eloquent" user provider to disable all global scopes on the User model, which includes the KnownUsersAccessScope.
        // see \Illuminate\Auth\CreatesUserProviders::createEloquentProvider where this normally happens
        $this->app->resolving('auth', static function (AuthManager $authManager): void {
            $authManager->provider('eloquent', static function (Application $app, array $config) {
                return (new EloquentUserProvider($app['hash'], $config['model']))->withQuery(static function (Builder $query): void {
                    $query->withoutGlobalScopes();
                });
            });
        });
    }

    private function registerClockForInterface(): void
    {
        $this->app->singleton(CarbonClockInterface::class, static fn () => new CarbonClock());
        $this->app->alias(CarbonClockInterface::class, ClockInterface::class);
    }
}
