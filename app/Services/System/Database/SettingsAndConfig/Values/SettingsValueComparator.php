<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig\Values;

use App\Utils\Casts\AbstractCastableObject;
use Carbon\CarbonInterface;

/**
 * Compares two typed property values of settings/config objects for equality. Generic
 * over every {@see AbstractCastableObject} — currently the engine of the user-settings
 * diff-based persistence, equally applicable to any future config-side diffing.
 *
 * The comparison operates **exclusively on typed (deserialized) values, never on stored
 * strings**: serialized comparisons would classify every `encrypted:` property as
 * "always changed", because each serialization produces a fresh random ciphertext for the
 * same plaintext. Typed comparison sees the decrypted plaintext and compares it correctly.
 *
 * Type handling:
 *
 * - `null` vs `null` is equal; `null` vs any value is different.
 * - Scalars, arrays and enums compare with strict `===` (arrays are order-sensitive by
 *   design — `['a','b']` and `['b','a']` are different values).
 * - Dates (`CarbonInterface`) compare via `equalTo()` — both sides originate from the
 *   same cast round-trip, so the comparison is instant-exact without serialization
 *   format concerns.
 * - Nested {@see AbstractCastableObject} instances compare recursively property-by-
 *   property with the same rules (the encrypted rule applies recursively inside them).
 * - Any other object type (e.g. custom-caster products the comparator does not
 *   understand) is treated as **always different** — the row is written on every save.
 *   Sparser storage is an optimization, not a correctness requirement.
 *
 * @internal the comparator is a collaborator of the diff-based persistence; use
 *           {@see SettingsDiff} via {@see diffObjects()} instead of calling
 *           {@see valuesEqual()} directly
 */
final class SettingsValueComparator
{
    /**
     * Compares two castable objects of the same class property-by-property and returns
     * the properties whose typed values differ.
     */
    public function diffObjects(AbstractCastableObject $left, AbstractCastableObject $right): SettingsDiff
    {
        $differing = [];

        foreach (array_keys($left->toStringArray()) as $property) {
            if (!$this->valuesEqual($this->readProperty($left, $property), $this->readProperty($right, $property))) {
                $differing[] = $property;
            }
        }

        return new SettingsDiff($differing);
    }

    /**
     * Compares two typed property values for equality (see class docblock for the rules).
     */
    public function valuesEqual(mixed $left, mixed $right): bool
    {
        if (null === $left || null === $right) {
            return $left === $right;
        }

        if ($left instanceof CarbonInterface && $right instanceof CarbonInterface) {
            return $left->equalTo($right);
        }

        // Scalars, arrays (order-sensitive) and enums compare with strict identity.
        // Enums must be caught here: they are objects, but their equality is case
        // identity — not the "unknown object type" rule below.
        if (
            (!\is_object($left) && !\is_object($right))
            || ($left instanceof \UnitEnum && $right instanceof \UnitEnum)
        ) {
            return $left === $right;
        }

        if ($left instanceof AbstractCastableObject && $right instanceof AbstractCastableObject) {
            return $this->castableObjectsEqual($left, $right);
        }

        // Objects of a type the comparator does not understand (custom caster
        // products) or mixed object/non-object pairs: always different, so the
        // value is written on every save.
        return false;
    }

    /**
     * Compares two nested castable objects property-by-property.
     */
    private function castableObjectsEqual(AbstractCastableObject $left, AbstractCastableObject $right): bool
    {
        foreach (array_keys($left->toStringArray()) as $property) {
            if (!$this->valuesEqual($this->readProperty($left, $property), $this->readProperty($right, $property))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reads a property value, returning null when the property is declared but not
     * initialized (e.g. a typed property without a default hydrated from an empty map).
     */
    private function readProperty(AbstractCastableObject $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);

        return $reflection->isInitialized($object)
            ? $reflection->getValue($object)
            : null;
    }
}
