<?php

declare(strict_types=1);

namespace App\Utils\Casts\Contracts;

/**
 * Interface for custom cast handlers used with {@see AbstractCastableObject}.
 *
 * Implement this interface to define how a non-standard PHP value (e.g. a value object,
 * a custom collection, a locale-aware date) is converted to and from its stored string
 * representation in the database.
 *
 * Register a custom caster on a property via {@see CastedValue}:
 * ```php
 * #[CastedValue(MyCaster::class)]
 * public MyType $value;
 *
 * // With constructor arguments passed after a colon, comma-separated:
 * #[CastedValue(MyCaster::class . ':arg1,arg2')]
 * public MyType $value;
 * ```
 *
 * The caster receives the full parent object on every call, so it can read sibling properties
 * for context-dependent serialization (e.g. reading a locale or timezone from another field).
 *
 * Example — a caster that uses a sibling `$locale` property to parse a localized date string:
 * ```php
 * class LocaleDateCast implements CastsValue
 * {
 *     public function get(object $object, string $stored): \Carbon\Carbon
 *     {
 *         $locale = $object->locale ?? 'de';
 *         return \Carbon\Carbon::createFromLocaleFormat('L', $locale, $stored);
 *     }
 *
 *     public function set(object $object, mixed $value): string
 *     {
 *         return $value instanceof \DateTimeInterface
 *             ? $value->format('d.m.Y')
 *             : (string) $value;
 *     }
 * }
 * ```
 *
 * @see AbstractCastableObject
 * @see CastedValue
 * @api
 */
interface CastsValue
{
    /**
     * Convert the raw stored string value into its PHP representation.
     *
     * Called during {@see AbstractCastableObject::fromStringArray()} for every property
     * whose cast type resolves to this caster class.
     *
     * @param object $object The parent castable object instance. Read-only during hydration —
     *                       sibling properties that appear later in the class declaration may
     *                       not yet be populated when this is called.
     * @param string $stored the raw string as it was read from the database
     * @param string $property the name of the property being cast.
     *
     * @return mixed the PHP-typed value to assign to the property
     */
    public function get(object $object, string $stored, string $property): mixed;

    /**
     * Convert a PHP value into its stored string representation.
     *
     * Called during {@see AbstractCastableObject::toStringArray()} for every property
     * whose cast type resolves to this caster class.
     *
     * @param object $object The parent castable object instance. Useful when the serialization
     *                       format depends on a sibling property (e.g. a locale or timezone).
     * @param mixed $value the current PHP value of the property
     * @param string $property the name of the property being cast.
     *
     * @return string the serialized string to persist in the database
     */
    public function set(object $object, mixed $value, string $property): string;
}
