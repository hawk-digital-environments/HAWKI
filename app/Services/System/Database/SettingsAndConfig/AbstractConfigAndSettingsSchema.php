<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig;

use App\Services\System\Database\SettingsAndConfig\Contracts\ConfigAndSettingsSchemaInterface;
use App\Services\System\Database\SettingsAndConfig\Exceptions\ConfigAndSettingsSchemaException;
use App\Utils\Casts\AbstractCastableObject;

/**
 * Shared mechanics for **both** schema facades ({@see ConfigSchema} and
 * {@see UserSettingsSchema}): class validation, default-instance creation and
 * pending-value serialization. The concrete facades own the row routing — they know
 * their target table, their repository and their seeding behaviour.
 *
 * The facades are static migration tooling (the `Schema::`-style equivalent for
 * config/settings rows): they resolve their repositories from the container at call
 * time, which is available in migration context. They are not part of request-time code.
 */
abstract class AbstractConfigAndSettingsSchema implements ConfigAndSettingsSchemaInterface
{
    /**
     * Asserts the managed class extends the required base (either
     * {@see \App\Services\Config\AbstractConfig} or
     * {@see \App\Services\Users\Settings\AbstractUserSettings}) and returns it narrowed
     * to a typed class-string, so the concrete facades can call the class's static
     * methods type-safely.
     *
     * @template T of AbstractCastableObject
     *
     * @param class-string<T> $requiredBaseClass
     *
     * @return class-string<T>
     */
    protected static function assertClassExtends(string $settingsClass, string $requiredBaseClass): string
    {
        if (!is_a($settingsClass, $requiredBaseClass, true)) {
            throw ConfigAndSettingsSchemaException::forInvalidClass($settingsClass, $requiredBaseClass);
        }

        return $settingsClass;
    }

    /**
     * Returns the typed defaults instance of the class — `fromStringArray([])` produces
     * an instance where every property holds its declared PHP default.
     *
     * @param class-string<AbstractCastableObject> $settingsClass
     */
    protected static function defaults(string $settingsClass): AbstractCastableObject
    {
        return $settingsClass::fromStringArray([]);
    }

    /**
     * Serializes queued (explicitly set) typed values into their stored string form,
     * keyed by property name. Values are round-tripped through the class's casts via
     * `fromArray()`, so encryption and custom casters apply exactly like on a save.
     *
     * @param class-string<AbstractCastableObject> $settingsClass
     * @param array<string, mixed>                 $pending
     *
     * @return array<string, null|string>
     */
    protected static function serializePending(string $settingsClass, array $pending): array
    {
        if ([] === $pending) {
            return [];
        }

        foreach (array_keys($pending) as $property) {
            if (!property_exists($settingsClass, $property)) {
                throw ConfigAndSettingsSchemaException::forUnknownPendingProperty($settingsClass, $property);
            }
        }

        return array_intersect_key($settingsClass::fromArray($pending)->toStringArray(), $pending);
    }
}
