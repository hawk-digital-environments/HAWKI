<?php
declare(strict_types=1);


namespace App\Services\Config;


use App\Utils\Casts\AbstractCastableObject;

/**
 * Your config class MUST implement a static ::make() method, that returns an instance of the config class.
 * The ::make() method is loaded via ServiceLocator::call() in ConfigService, so you can use constructor injection to get dependencies.
 */
abstract class AbstractConfig extends AbstractCastableObject
{
    /**
     * Derives the DB namespace from the containing plugin's Composer package name.
     * e.g. hawk/deepl-plugin → "hawk-deepl-plugin"
     *
     * The namespace is anchored to the Composer package name, not the PHP class name.
     * This means:
     * - Collision-free by design — Composer package names are globally unique
     * - Survives PHP class renames without requiring a ConfigSchema::rename() migration
     * - Human-readable in the database (no md5 hashes)
     *
     * Resolution uses PluginAwareTrait: either the explicit PLUGIN_NAME constant
     * or namespace prefix matching via InstalledPlugins::guessPlugin().
     *
     * This method is final; there is no per-class namespace override.
     */
    final public static function namespace(): string
    {
        return 'hawki-core';
    }
}
