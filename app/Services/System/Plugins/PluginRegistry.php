<?php

declare(strict_types=1);

namespace App\Services\System\Plugins;

use App\Services\System\Plugins\Exceptions\PluginNotFoundException;

/**
 * The sole public API for anything that needs to introspect installed plugins at runtime.
 *
 * The registry receives its raw plugin data (the content of `bootstrap/cache/plugins.php`)
 * via constructor — no file I/O, no container access — so it can be instantiated before
 * the application boots. Plugins are instantiated lazily on first access, and the core
 * application ({@see HawkiCorePlugin}) is always registered as a built-in entry, so
 * `App\` classes resolve to the `hawki-core` namespace even with an empty plugin cache.
 *
 * Classes resolve to their owning plugin via `guess()`: the class's fully-qualified name
 * is matched against all registered plugin namespace prefixes, longest prefix wins. The
 * short `App\` core prefix therefore matches last, so plugin namespaces take priority.
 *
 * For testing, construct the registry with a mock raw config array, or call
 * {@see InstalledPlugins::reset()} to clear the cached singleton.
 *
 * @api
 */
class PluginRegistry
{
    /**
     * Instantiated plugins, keyed by Composer package name. Lazily populated on first access.
     *
     * @var array<string, AbstractHawkiPlugin>
     */
    private array $plugins = [];

    /**
     * Namespace prefix → package name, sorted longest-prefix-first for correct matching.
     *
     * @var array<string, string>
     */
    private array $namespacePrefixIndex = [];
    private bool $initialized = false;

    /**
     * @param array<string, array{
     *     class: class-string<AbstractHawkiPlugin>,
     *     namespace: string,
     *     namespaces: list<string>,
     *     version: string,
     *     path: string,
     * }> $rawConfig The plugin cache data from `bootstrap/cache/plugins.php`, keyed by
     *               Composer package name. Empty while no plugin cache exists.
     */
    public function __construct(private readonly array $rawConfig = [])
    {
    }

    /**
     * Returns all installed plugins, including the built-in core plugin.
     *
     * @return list<AbstractHawkiPlugin>
     */
    public function all(): array
    {
        $this->initialize();

        return array_values($this->plugins);
    }

    /**
     * Returns a plugin by its Composer package name or PHP class name.
     *
     * @throws PluginNotFoundException when no installed plugin matches the identifier
     */
    public function get(string $pluginClassOrName): AbstractHawkiPlugin
    {
        $this->initialize();

        if (isset($this->plugins[$pluginClassOrName])) {
            return $this->plugins[$pluginClassOrName];
        }

        foreach ($this->plugins as $plugin) {
            if ($plugin::class === $pluginClassOrName) {
                return $plugin;
            }
        }

        throw PluginNotFoundException::forPluginIdentifier($pluginClassOrName);
    }

    /**
     * Returns the plugin that owns the given class via longest namespace prefix matching,
     * or null when the class does not belong to any installed plugin.
     *
     * Used by {@see PluginAwareTrait::getContainingPlugin()} as the implicit resolution
     * strategy when no explicit `#[PluginName]` attribute is declared.
     */
    public function guess(string $className): ?AbstractHawkiPlugin
    {
        $this->initialize();

        foreach ($this->namespacePrefixIndex as $prefix => $packageName) {
            if (str_starts_with($className, $prefix)) {
                return $this->plugins[$packageName];
            }
        }

        return null;
    }

    /**
     * Returns the flat list of ServiceProvider class names from all installed plugins.
     * Called in `bootstrap/app.php` before the container exists.
     *
     * @return list<class-string>
     */
    public function getServiceProviders(): array
    {
        return array_merge(...array_map(static fn (AbstractHawkiPlugin $plugin) => $plugin->getServiceProviders(), $this->all()));
    }

    /**
     * Returns the translation loaders from all installed plugins, in load order.
     * Called during service provider registration (container available).
     *
     * @return list<object>
     */
    public function getTranslationLoaders(): array
    {
        return array_merge(...array_map(static fn (AbstractHawkiPlugin $plugin) => $plugin->getTranslationLoaders(), $this->all()));
    }

    /**
     * Returns the event listener directories from all installed plugins.
     * Called in `bootstrap/app.php`.
     *
     * @return list<string>
     */
    public function getEventListenerPaths(): array
    {
        return array_merge(...array_map(static fn (AbstractHawkiPlugin $plugin) => $plugin->getEventListenerPaths(), $this->all()));
    }

    /**
     * Lazily instantiates all plugins (cache entries + the built-in core plugin) and builds
     * the namespace prefix index, sorted longest-first so plugin prefixes win over the
     * short `App\` core prefix.
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        foreach ($this->rawConfig as $packageName => $entry) {
            $this->plugins[$packageName] = new $entry['class']($packageName, $entry['version']);
        }

        $core = new HawkiCorePlugin();
        $this->plugins[$core->getPluginName()] = $core;

        $prefixes = [HawkiCorePlugin::CLASS_NAMESPACE_PREFIX => $core->getPluginName()];

        foreach ($this->rawConfig as $packageName => $entry) {
            foreach ($entry['namespaces'] as $prefix) {
                $prefixes[$prefix] = $packageName;
            }
        }

        uksort($prefixes, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $this->namespacePrefixIndex = $prefixes;
    }
}
