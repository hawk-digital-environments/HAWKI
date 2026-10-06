<?php

declare(strict_types=1);

namespace App\Services\System\Plugins;

use Illuminate\Support\ServiceProvider;

/**
 * Base contract for every HAWKI plugin.
 *
 * Plugin classes must be constructible before the Laravel container exists: the constructor
 * accepts only the package name and version, both supplied by the plugin registry from the
 * plugin cache. Never add constructor dependencies — service wiring happens through the
 * plugin's service providers, not through the plugin class itself.
 *
 * The storage-safe namespace used by config and settings rows is derived deterministically
 * from the Composer package name (`hawk/deepl-plugin` → `hawk-deepl-plugin`), so plugin
 * config classes and settings classes automatically group under their plugin without any
 * manual namespace string.
 *
 * The `get*()` methods for service providers, translation loaders and listener paths are
 * the noop defaults for the current phase; they become meaningful once the real plugin
 * system lands.
 *
 * @api
 */
abstract class AbstractHawkiPlugin
{
    public function __construct(
        private readonly string $pluginName,
        private readonly string $pluginVersion,
    ) {
    }

    /**
     * The Composer package name of this plugin, e.g. `'hawk/deepl-plugin'`.
     */
    final public function getPluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * The installed version of this plugin.
     */
    final public function getPluginVersion(): string
    {
        return $this->pluginVersion;
    }

    /**
     * The storage-safe namespace of this plugin, derived from the Composer package name.
     *
     * `'hawk/deepl-plugin'` becomes `'hawk-deepl-plugin'`. This is the namespace under
     * which plugin config and plugin user-settings rows are grouped in the database, and
     * the key the public config/settings API responses use for the plugin's namespace.
     *
     * The method is final: the namespace is anchored to the package name by design, so
     * plugins cannot accidentally collide with another plugin's (or the core's) namespace.
     */
    final public function getNamespace(): string
    {
        return str_replace('/', '-', $this->pluginName);
    }

    /**
     * The ServiceProvider classes this plugin contributes.
     *
     * Called once from `bootstrap/app.php` before the container is fully booted.
     *
     * @return list<class-string<ServiceProvider>>
     */
    final public function getServiceProviders(): array
    {
        return [];
    }

    /**
     * The translation loader instances this plugin contributes.
     *
     * Called during service provider registration (container available).
     *
     * @return list<object>
     */
    final public function getTranslationLoaders(): array
    {
        return [];
    }

    /**
     * The directories containing this plugin's event listeners.
     *
     * Called from `bootstrap/app.php`; each returned path is added to Laravel's event
     * discovery alongside the core listener directories.
     *
     * @return list<string>
     */
    final public function getEventListenerPaths(): array
    {
        return [];
    }
}
