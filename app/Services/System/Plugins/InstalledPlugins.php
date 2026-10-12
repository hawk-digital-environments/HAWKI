<?php

declare(strict_types=1);

namespace App\Services\System\Plugins;

/**
 * Static bootstrap wrapper around the {@see PluginRegistry}.
 *
 * Modelled after `Composer\InstalledVersions`: it exists solely for the two places where
 * constructor injection is not available — `bootstrap/app.php` (where no container exists
 * yet) and {@see PluginAwareTrait} (which resolves in a static context). All other code
 * injects the registry via constructor.
 *
 * @internal Not part of the public plugin API. Use {@see PluginRegistry} via constructor
 *           injection instead.
 */
final class InstalledPlugins
{
    private static ?PluginRegistry $registry = null;
    private static string $cacheFile = __DIR__ . '/../../../../bootstrap/cache/plugins.php';

    /**
     * Returns a plugin by its Composer package name or PHP class name.
     * Throws when not found.
     */
    public static function getPlugin(string $pluginClassOrName): AbstractHawkiPlugin
    {
        return self::getRegistry()->get($pluginClassOrName);
    }

    /**
     * Returns the plugin that owns the given class via longest namespace prefix matching,
     * or null if no plugin matches.
     */
    public static function guessPlugin(string $className): ?AbstractHawkiPlugin
    {
        return self::getRegistry()->guess($className);
    }

    /**
     * Returns the plugin registry singleton.
     *
     * Called once from `bootstrap/app.php`; the returned instance is then bound into the
     * container via `withSingletons()` so constructor injection everywhere else resolves
     * the same object.
     */
    public static function getRegistry(): PluginRegistry
    {
        return self::$registry ??= new PluginRegistry(is_file(self::$cacheFile) ? require self::$cacheFile : []);
    }

    // -------------------------------------------------------
    // Test helpers
    // -------------------------------------------------------

    /**
     * Overrides the plugin cache file to read from, and resets the cached registry.
     */
    public static function setCacheFile(string $path): void
    {
        self::$cacheFile = $path;
        self::$registry = null;
    }

    /**
     * Returns the currently configured plugin cache file path.
     */
    public static function getCacheFile(): string
    {
        return self::$cacheFile;
    }

    /**
     * Resets the cached registry singleton, so the next access re-reads the cache file.
     */
    public static function reset(): void
    {
        self::$registry = null;
    }
}
