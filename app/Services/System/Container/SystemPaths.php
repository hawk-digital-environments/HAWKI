<?php
declare(strict_types=1);


namespace App\Services\System\Container;


use Illuminate\Foundation\Application;

/**
 * Thin wrapper around Laravel's Application that exposes path-resolution methods.
 *
 * Inject this class instead of the full Application whenever a service needs to resolve
 * file-system paths within the Laravel installation — e.g. to locate config, storage,
 * resource, or public files — without hard-coding them. Because this class is small and
 * concrete, it can be swapped with a mock in unit tests without bootstrapping the entire
 * framework.
 *
 * Usage example:
 * ```php
 * readonly class MyDataStore
 * {
 *     public function __construct(
 *         private SystemPaths $paths,
 *     ) {}
 *
 *     public function getDataFile(): string
 *     {
 *         return $this->paths->storagePath('app/data/my-file.json');
 *     }
 * }
 * ```
 */
readonly class SystemPaths
{
    public function __construct(
        private Application $application
    )
    {
    }

    /**
     * Returns the absolute path to the `app/` directory, optionally joined with `$path`.
     */
    public function path(string $path = ''): string
    {
        return $this->application->path($path);
    }

    /**
     * Returns the root path of the Laravel installation (where `composer.json` lives),
     * optionally joined with `$path`.
     */
    public function basePath(string $path = ''): string
    {
        return $this->application->basePath($path);
    }

    /**
     * Returns the absolute path to the `bootstrap/` directory, optionally joined with `$path`.
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->application->bootstrapPath($path);
    }

    /**
     * Returns the path to the service-provider manifest used during Laravel's bootstrap
     * phase (`bootstrap/providers.php`).
     */
    public function getBootstrapProvidersPath(): string
    {
        return $this->application->getBootstrapProvidersPath();
    }

    /**
     * Returns the absolute path to the `config/` directory, optionally joined with `$path`.
     */
    public function configPath(string $path = ''): string
    {
        return $this->application->configPath($path);
    }

    /**
     * Returns the absolute path to the `database/` directory, optionally joined with `$path`.
     */
    public function databasePath(string $path = ''): string
    {
        return $this->application->databasePath($path);
    }

    /**
     * Returns the absolute path to the `public/` (web root) directory,
     * optionally joined with `$path`.
     */
    public function publicPath(string $path = ''): string
    {
        return $this->application->publicPath($path);
    }

    /**
     * Returns the absolute path to the `storage/` directory, optionally joined with `$path`.
     */
    public function storagePath(string $path = ''): string
    {
        return $this->application->storagePath($path);
    }

    /**
     * Returns the absolute path to the `resources/` directory, optionally joined with `$path`.
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->application->resourcePath($path);
    }
}
