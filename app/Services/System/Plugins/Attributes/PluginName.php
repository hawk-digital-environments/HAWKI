<?php

declare(strict_types=1);

namespace App\Services\System\Plugins\Attributes;

/**
 * Explicitly assigns a class to a plugin by its Composer package name.
 *
 * Applied to classes that use {@see \App\Services\System\Plugins\PluginAwareTrait} when the
 * implicit namespace-prefix resolution cannot (or should not) guess the owning plugin —
 * e.g. because the class lives outside the plugin's main namespace, or to make the
 * ownership explicit and skip the guessing cost.
 *
 * Usage:
 * ```php
 * #[PluginName('hawk/deepl-plugin')]
 * class TranslationCache extends \App\Utils\Casts\AbstractCastableObject
 * {
 *     // ...
 * }
 * ```
 *
 * The attribute takes priority over the implicit `PluginRegistry::guess()` resolution; both
 * strategies are documented on {@see \App\Services\System\Plugins\PluginAwareTrait}.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PluginName
{
    public function __construct(
        /**
         * The Composer package name of the owning plugin, e.g. `'hawk/deepl-plugin'`.
         */
        public readonly string $name,
    ) {
    }
}
