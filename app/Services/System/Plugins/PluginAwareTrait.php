<?php

declare(strict_types=1);

namespace App\Services\System\Plugins;

use App\Services\System\Plugins\Attributes\PluginName;
use App\Services\System\Plugins\Exceptions\PluginNotFoundException;

/**
 * Resolves the plugin that owns the using class.
 *
 * For classes that cannot inject the {@see PluginRegistry} via constructor — config and
 * user-settings classes (static `namespace()` resolution) and later `PluginModel`
 * subclasses (Eloquent instantiation). Two resolution strategies, tried in order:
 *
 * 1. **Explicit:** a `#[PluginName('hawk/deepl-plugin')]` attribute on the class resolves
 *    the plugin directly via {@see InstalledPlugins::getPlugin()}. Zero-cost lookup and
 *    the recommended way for classes whose namespace conventions do not apply.
 * 2. **Implicit:** {@see InstalledPlugins::guessPlugin()} matches the class's
 *    fully-qualified name against all registered plugin namespace prefixes, longest
 *    prefix wins. `App\` classes resolve to the core plugin by this route.
 *
 * Resolution results are cached statically per concrete class, so they run at most once
 * per class and request lifecycle. The cache is keyed by class name on purpose: the
 * trait is used by abstract bases (e.g. the config and user-settings base classes),
 * and a bare static property would be shared by every subclass — poisoning resolution
 * across classes owned by different plugins.
 *
 * @api
 */
trait PluginAwareTrait
{
    /**
     * The containing plugins resolved so far, keyed by concrete class name. A null entry
     * means "explicitly reset via setContainingPlugin(null) — resolve again".
     *
     * @var array<class-string, null|AbstractHawkiPlugin>
     */
    private static array $containingPlugins = [];

    /**
     * Test helper to override the containing plugin for the using class.
     *
     * Use with care — this is a per-class (effectively global for that class) change that
     * affects every other resolution of the same class. Set to null to reset to normal
     * resolution behaviour.
     */
    public static function setContainingPlugin(?AbstractHawkiPlugin $plugin): void
    {
        self::$containingPlugins[static::class] = $plugin;
    }

    /**
     * Returns the plugin that owns the using class.
     *
     * @throws PluginNotFoundException when no installed plugin matches the class and no
     *                                 explicit `#[PluginName]` attribute is declared
     */
    public static function getContainingPlugin(): AbstractHawkiPlugin
    {
        $class = static::class;

        if (\array_key_exists($class, self::$containingPlugins)) {
            $cached = self::$containingPlugins[$class];

            if (null !== $cached) {
                return $cached;
            }

            // An explicit null resets the resolution — resolve again below.
            unset(self::$containingPlugins[$class]);
        }

        $attributes = (new \ReflectionClass($class))->getAttributes(PluginName::class);

        if ([] !== $attributes) {
            return self::$containingPlugins[$class] = InstalledPlugins::getPlugin($attributes[0]->newInstance()->name);
        }

        $plugin = InstalledPlugins::guessPlugin($class);

        if (null === $plugin) {
            throw PluginNotFoundException::forUnresolvableClass($class);
        }

        return self::$containingPlugins[$class] = $plugin;
    }
}
