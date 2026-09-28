<?php

declare(strict_types=1);

namespace App\Services\Users\Settings;

use App\Services\System\Plugins\PluginAwareTrait;
use App\Utils\Casts\AbstractCastableObject;

/**
 * Base class for all user-settings objects.
 *
 * Concrete subclasses declare `public` typed properties with defaults — exactly like
 * {@see AbstractConfig} subclasses — and are hydrated per user via
 * {@see UserSettingsService::get()}. Properties missing from the user's stored rows retain
 * their declared PHP default values.
 *
 * Class identity mirrors the config contract: {@see namespace()} (the owning package,
 * derived via {@see PluginAwareTrait} — `'hawki-core'` for core classes, the plugin slug
 * for plugin classes) plus {@see publicKey()} (the class's unique key within its
 * namespace, used to group the class inside the namespace-scoped `user-settings`
 * JSON:API resource).
 *
 * @api
 *
 * @see UserSettingsService
 * @see AbstractCastableObject for property hydration and serialization
 */
abstract class AbstractUserSettings extends AbstractCastableObject
{
    use PluginAwareTrait;

    /**
     * Returns the namespace that groups this settings class.
     *
     * Derived from the owning plugin via {@see PluginAwareTrait}: every settings class
     * inside the `App\` namespace resolves to `'hawki-core'`, plugin settings classes to
     * their plugin's storage-safe namespace. The method is `final` so the namespace is
     * always anchored to the owning package.
     */
    final public static function namespace(): string
    {
        return static::getContainingPlugin()->getNamespace();
    }

    /**
     * The unique key for this settings class within its namespace.
     *
     * Must be snake_case, must not include the namespace prefix, and must be globally
     * unique across all registered settings classes (the JSON:API schema fields are
     * keyed by it). Example: `'core'`.
     */
    abstract public static function publicKey(): string;

    /**
     * Returns the typed property values of this instance, keyed by property name — the
     * wire format of the user-settings API.
     *
     * User settings are always public, so there is no visibility gating and no secret
     * filtering here (unlike the config system's `toPublicArray()`). Backed enums
     * serialize to their backing values, matching the frontend's wire format.
     * Properties that are declared but not initialized are returned as null.
     *
     * @return array<string, mixed>
     */
    final public function toPublicValues(): array
    {
        $values = [];

        foreach ((new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $values[$property->getName()] = $property->isInitialized($this)
                ? $property->getValue($this)
                : null;
        }

        return $values;
    }
}
