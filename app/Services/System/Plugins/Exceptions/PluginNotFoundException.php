<?php

declare(strict_types=1);

namespace App\Services\System\Plugins\Exceptions;

use App\Services\System\Plugins\Attributes\PluginName;

/**
 * Thrown when a plugin cannot be found — either because an identifier (package or class
 * name) does not match any installed plugin, or because no installed plugin's namespace
 * owns the class that tried to resolve its containing plugin.
 *
 * Both cases are configuration problems: either the plugin is not installed (check the
 * plugin cache at `bootstrap/cache/plugins.php`), or the class is missing an explicit
 * `#[PluginName]` attribute that would assign it to a plugin.
 */
class PluginNotFoundException extends \RuntimeException implements PluginsExceptionInterface
{
    /**
     * Creates the exception for a plugin identifier (Composer package name or PHP class
     * name) that does not match any installed plugin.
     */
    public static function forPluginIdentifier(string $identifier): self
    {
        return new self(\sprintf(
            'No installed plugin found for identifier "%s". Either the plugin is not installed,'
            . ' or the plugin cache at "bootstrap/cache/plugins.php" is outdated and needs to be rebuilt.',
            $identifier,
        ));
    }

    /**
     * Creates the exception for a class whose containing plugin could not be resolved —
     * no installed plugin namespace matches the class name, and no `#[PluginName]`
     * attribute is set on the class.
     */
    public static function forUnresolvableClass(string $className): self
    {
        return new self(\sprintf(
            'Could not resolve the plugin owning the class "%s": no installed plugin namespace'
            . ' matches the class name, and the class does not declare a "%s" attribute.'
            . ' Add the attribute to the class, or move the class into a plugin namespace.',
            $className,
            PluginName::class,
        ));
    }
}
