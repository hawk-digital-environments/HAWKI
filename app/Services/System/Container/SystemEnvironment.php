<?php
declare(strict_types=1);


namespace App\Services\System\Container;


use Illuminate\Foundation\Application;

/**
 * Thin wrapper around Laravel's Application that exposes environment-detection methods.
 *
 * Inject this class instead of the full Application whenever a service needs to query the
 * current runtime context — e.g. whether it is running in the console, in a test suite,
 * or in production. Because this class is small and concrete, it can be swapped with a
 * mock in unit tests without bootstrapping the entire framework.
 *
 * Usage example:
 * ```php
 * readonly class MyScope
 * {
 *     public function __construct(
 *         private SystemEnvironment $environment,
 *     ) {}
 *
 *     public function apply(): void
 *     {
 *         if ($this->environment->runningInConsole()) {
 *             return; // skip HTTP-specific behaviour in CLI / queue workers
 *         }
 *     }
 * }
 * ```
 */
readonly class SystemEnvironment
{
    public function __construct(
        private Application $application
    )
    {
    }

    /**
     * Return the current environment name, or check whether it matches any of the given names.
     *
     * When called without arguments, returns the active environment name as a string
     * (e.g. `'local'`, `'production'`, `'testing'`).
     * When called with one or more names, returns `true` if the active environment matches
     * any of them, and `false` otherwise.
     */
    public function environment(string|array ...$environments): string|bool
    {
        return $this->application->environment(...$environments);
    }

    /**
     * Returns true when the application is running in the `local` environment.
     */
    public function isLocal(): bool
    {
        return $this->application->isLocal();
    }

    /**
     * Returns true when the application is running in the `production` environment.
     */
    public function isProduction(): bool
    {
        return $this->application->isProduction();
    }

    /**
     * Returns true when the current process was started from the CLI (Artisan commands,
     * queue workers, scheduled tasks) rather than an incoming HTTP request.
     *
     * Useful for suppressing HTTP-specific logic — e.g. aborting with 412 when no request
     * is present — in scopes that are also executed during console runs.
     */
    public function runningInConsole(): bool
    {
        return $this->application->runningInConsole();
    }

    /**
     * Returns true when PHPUnit is driving the application.
     */
    public function runningUnitTests(): bool
    {
        return $this->application->runningUnitTests();
    }

    /**
     * Returns true when `APP_DEBUG` is set to `true` in the application configuration.
     */
    public function hasDebugModeEnabled(): bool
    {
        return $this->application->hasDebugModeEnabled();
    }
}
