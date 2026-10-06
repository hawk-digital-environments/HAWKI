<?php

declare(strict_types=1);

namespace App\Services\System\Plugins;

/**
 * Synthetic plugin representing the HAWKI core application.
 *
 * Every class inside the `App\` namespace resolves to this plugin via the registry's
 * namespace-prefix index — so core config classes, user-settings classes and (later) plugin
 * models derive the namespace `'hawki-core'` from it, exactly like plugin classes derive
 * theirs from their Composer package name. Because the prefix index sorts longest-first,
 * plugin namespaces always win over the short `App\` core prefix.
 *
 * The registry registers this plugin as a built-in entry in every instance, independent of
 * the plugin cache; it can never be uninstalled.
 *
 * @internal the core plugin is a technical identity, not something to extend
 */
final class HawkiCorePlugin extends AbstractHawkiPlugin
{
    /**
     * The "package name" of the core application. Deliberately not a Composer-style
     * `vendor/package` pair: the derived namespace ({@see getNamespace()}) must be
     * exactly `'hawki-core'` — the established namespace of all core config and
     * settings — and the derivation replaces slashes with dashes.
     */
    public const string PLUGIN_NAME = 'hawki-core';

    /**
     * The namespace prefix under which all core classes live. Registered in the plugin
     * registry's prefix index so `guess()` resolves `App\...` classes to this plugin.
     */
    public const string CLASS_NAMESPACE_PREFIX = 'App\\';

    public function __construct()
    {
        parent::__construct(self::PLUGIN_NAME, '0.0.0');
    }
}
