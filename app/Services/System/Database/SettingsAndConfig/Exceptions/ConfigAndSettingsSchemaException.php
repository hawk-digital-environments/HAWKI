<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig\Exceptions;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Thrown when the settings/config schema tooling is misused — e.g. a class that is not a
 * settings/config object is passed to a schema facade, or the blueprint is asked for a
 * property the class does not declare.
 *
 * These are programming errors inside migrations; fix the migration code.
 */
class ConfigAndSettingsSchemaException extends \LogicException implements SettingsAndConfigExceptionInterface
{
    /**
     * Creates the exception for a class that does not extend the required base class.
     */
    public static function forInvalidClass(string $settingsClass, string $requiredBaseClass): self
    {
        return new self(\sprintf(
            'The class "%s" must extend "%s" to be managed by the settings schema tooling.',
            $settingsClass,
            $requiredBaseClass,
        ));
    }

    /**
     * Creates the exception for a blueprint property access on a class that does not
     * declare the property — the typed `__get()` needs the property to exist; use
     * `getRaw()` for properties that were removed from the class.
     */
    public static function forUnknownProperty(string $settingsClass, string $property): self
    {
        return new self(\sprintf(
            'The class "%s" does not declare a property "%s". Use getRaw() for properties that were'
            . ' removed from the class, or check the property name.',
            $settingsClass,
            $property,
        ));
    }

    /**
     * Creates the exception for a pending value that was set on the blueprint for a
     * property the class does not declare.
     */
    public static function forUnknownPendingProperty(string $settingsClass, string $property): self
    {
        return new self(\sprintf(
            'The class "%s" does not declare a property "%s" — the migration closure set a value for it.'
            . ' Rename or re-cast the property in the class first, or fix the property name.',
            $settingsClass,
            $property,
        ));
    }

    /**
     * Creates the exception for an operation that cannot run a migration closure —
     * e.g. `UserSettingsSchema::create()` with a closure: there are no per-user rows at
     * install time, so the closure would silently never run. Passing one is rejected
     * instead of silently ignored.
     */
    public static function forUnsupportedClosure(string $schemaClass, string $method): self
    {
        return new self(\sprintf(
            '%s::%s() does not accept a migration closure — there is nothing for it to transform.'
            . ' Use %s::update() for structural transforms of existing rows.',
            $schemaClass,
            $method,
            $schemaClass,
        ));
    }

    /**
     * Creates the exception when serializing pending blueprint values failed because
     * the class is no longer a castable object.
     */
    public static function forNonCastableClass(string $settingsClass): self
    {
        return new self(\sprintf(
            'The class "%s" must extend "%s".',
            $settingsClass,
            AbstractCastableObject::class,
        ));
    }
}
