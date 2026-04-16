<?php

declare(strict_types=1);

namespace App\Utils\Casts;

use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Values\ResolvedCaster;

/**
 * Base class for typed, serializable value objects whose properties are populated from and
 * persisted to a flat string map (e.g. database rows, environment files, JSON config).
 *
 * Instances can only be created through the static factory {@see AbstractCastableObject::fromStringArray()},
 * which converts raw stored strings into fully-typed PHP values. The reverse direction is covered by
 * {@see AbstractCastableObject::toArrayList()}, which serializes all public properties back to strings.
 * Properties that are not present in the input array during hydration retain their declared PHP default values.
 *
 * ---
 *
 * ## Defining a castable object
 *
 * Extend this class and declare `public` properties with type hints and defaults.
 * Scalar and array properties (`int`, `float`, `bool`, `string`, `array`) are cast automatically
 * from their PHP type hint — no annotation needed. Add {@see CastedValue} only when the default
 * inference is insufficient: encrypted values, custom casters, or enum storage format overrides.
 * A property with a non-scalar, non-array type hint and no annotation throws a {@see \LogicException}
 * at runtime to guide the developer.
 *
 * ```php
 * class AiConfig extends AbstractCastableObject
 * {
 *     // No annotation needed: int/bool/array type hints are inferred automatically.
 *     public int $max_tokens = 4096;
 *     public bool $stream = true;
 *     public array $allowed_models = [];
 *
 *     #[CastedValue('encrypted:string')]
 *     public string $openai_api_key = '';
 *
 *     // No annotation needed: Carbon type hint is inferred as 'datetime' automatically.
 *     public Carbon $created_at;
 * }
 * ```
 *
 * Hydrate from a database row:
 * ```php
 * $config = AiConfig::fromStringArray([
 *     'max_tokens'     => '8192',
 *     'stream'         => '0',
 *     'allowed_models' => '["gpt-4","gpt-3.5-turbo"]',
 *     'openai_api_key' => '<laravel-ciphertext>',
 *     'created_at'     => '2024-01-15 09:30:00',
 * ]);
 *
 * $config->max_tokens;     // int(8192)
 * $config->stream;         // bool(false)
 * $config->allowed_models; // ['gpt-4', 'gpt-3.5-turbo']
 * $config->openai_api_key; // 'sk-...' (decrypted)
 * $config->created_at;     // Carbon instance
 * ```
 *
 * Serialize back to strings for persistence:
 * ```php
 * $row = $config->toArrayList();
 * // [
 * //   'max_tokens'     => '8192',
 * //   'stream'         => '0',
 * //   'allowed_models' => '["gpt-4","gpt-3.5-turbo"]',
 * //   'openai_api_key' => '<laravel-ciphertext>',
 * //   'created_at'     => '2024-01-15 09:30:00',
 * // ]
 * ```
 *
 * ---
 *
 * ## Supported cast types
 *
 * ### Primitive
 * - `'int'` / `'integer'`
 * - `'float'` / `'double'` / `'real'`
 * - `'bool'` / `'boolean'` — stored as `'1'` (true) / `'0'` (false)
 * - `'string'`
 *
 * ### Structured
 * - `'array'` / `'json'` — stored as a JSON string; decoded to `array` on read
 * - `'object'` — stored as a JSON string; decoded to `stdClass` on read
 *
 * ### Encrypted (requires `APP_KEY`)
 * Values are encrypted using Laravel's `Crypt` facade before storage and decrypted on read.
 * The suffix after `encrypted:` determines how the decrypted payload is interpreted:
 * - `'encrypted:string'` — stores a plain string ciphertext
 * - `'encrypted:array'` / `'encrypted:json'` — JSON-encodes the array before encrypting; decodes after decrypting
 * - `'encrypted:object'` — JSON-encodes the object before encrypting; decodes to `stdClass` after decrypting
 *
 * ### Date / Time
 * All date casts accept any value that `Carbon::parse()` can understand (string, int, DateTimeInterface).
 * - `'date'` — stored as `Y-m-d`; decoded to `Carbon` (start of day)
 * - `'immutable_date'` — same but returns `CarbonImmutable`
 * - `'datetime'` — stored as `Y-m-d H:i:s`; decoded to `Carbon`
 * - `'datetime:FORMAT'` — e.g. `'datetime:d.m.Y H:i'`; uses the given PHP date format for both directions
 * - `'immutable_datetime'` — stored as `Y-m-d H:i:s`; decoded to `CarbonImmutable`
 * - `'immutable_datetime:FORMAT'` — same but with a custom format; returns `CarbonImmutable`
 * - `'timestamp'` — stored as a Unix timestamp integer string; decoded to `int`
 *
 * ### Enums
 * Enum type hints are detected automatically — no annotation needed.
 * `BackedEnum` cases are stored by their backing value; `UnitEnum` cases are stored by their case name.
 *
 * ```php
 * enum Status: string { case Active = 'active'; case Inactive = 'inactive'; }
 * enum Direction { case North; case South; }
 *
 * class MyConfig extends AbstractCastableObject
 * {
 *     public Status $status = Status::Active;       // stored as 'active'
 *     public Direction $direction = Direction::North; // stored as 'North'
 * }
 * ```
 *
 * ### Custom casters
 * Implement {@see CastsValue} and use the fully-qualified class name as the cast type.
 * Constructor arguments can be appended after a colon, comma-separated.
 * The caster receives the full parent object so it can read sibling properties for context-dependent logic.
 *
 * ```php
 * class MoneyAmountCast implements CastsValue
 * {
 *     public function __construct(private readonly string $currency = 'EUR') {}
 *
 *     public function get(object $object, string $stored): Money
 *     {
 *         return new Money((int) $stored, $this->currency);
 *     }
 *
 *     public function set(object $object, mixed $value): string
 *     {
 *         return (string) $value->amount;
 *     }
 * }
 *
 * class PaymentConfig extends AbstractCastableObject
 * {
 *     // No constructor arg — uses default 'EUR'
 *     #[CastedValue(MoneyAmountCast::class)]
 *     public Money $limit;
 *
 *     // Passes 'USD' as the $currency constructor argument
 *     #[CastedValue(MoneyAmountCast::class . ':USD')]
 *     public Money $usd_reserve;
 * }
 * ```
 *
 * ---
 *
 * ## Notes
 *
 * - Only `public` non-static properties are considered by {@see getCasts()}, {@see fromStringArray()},
 *   and {@see toArrayList()}. Protected and private properties are ignored entirely.
 * - Properties without a {@see CastedValue} annotation are cast automatically from their PHP type hint
 *   for scalar/array builtins. Non-builtin types without an annotation throw a {@see \LogicException}.
 *   Properties with no type hint or type `mixed` are passed through as raw strings.
 * - The constructor is `protected` to prevent direct instantiation. Use {@see fromStringArray()} for raw
 *   stored strings or {@see fromArray()} for already-typed PHP values.
 * - The cast map is derived via reflection and cached statically per concrete class, so repeated calls
 *   to {@see getCasts()} carry no reflection overhead after the first call.
 * - Custom caster instances are also cached statically, keyed by class + arguments string.
 *
 * @see CastedValue
 * @see CastsValue
 * @api
 */
abstract class AbstractCastableObject
{
    private const SOURCE_STRING_ARRAY = 'stringArray';
    private const SOURCE_ARRAY = 'array';

    /**
     * Per-class cache of cast maps derived from {@see CastedValue} attributes.
     * Keyed by concrete class name; populated on first call to {@see getCasts()}.
     *
     * @var array<class-string, array<string, CastedValue>>
     */
    private static array $castsCache = [];

    /**
     * Cache of resolved {@see CastsValue} instances, keyed by "ClassName:arg-md5".
     * Shared across all instances and classes to avoid repeated instantiation.
     *
     * @var array<string, CastsValue>
     */
    private static array $casterCache = [];

    /**
     * Cache of ReflectionProperty instances for public properties, keyed by concrete class name.
     *
     * @var array<class-string, array<string, \ReflectionProperty>>
     */
    private static array $propertyReflectionsCache = [];

    /**
     * Populates public properties from an attribute map.
     *
     * Properties not present in $attributes retain their declared PHP default values.
     * Null values are stored as-is without casting.
     *
     * @param array<string, mixed> $attributes flat key → value map
     * @param self::SOURCE_* $sourceType SOURCE_STRING_ARRAY — values are raw stored strings and will be
     *                                         cast via {@see valueFromString}. SOURCE_ARRAY — values are
     *                                         already typed PHP values and are assigned directly.
     */
    final private function __construct(array $attributes, string $sourceType = self::SOURCE_STRING_ARRAY)
    {
        foreach ($this->getPropertyReflections() as $name => $prop) {
            if (!\array_key_exists($name, $attributes) || $attributes[$name] === null) {
                continue;
            }

            $prop->setValue(
                $this,
                self::SOURCE_STRING_ARRAY === $sourceType
                    ? $this->valueFromString($name, $attributes[$name])
                    : $attributes[$name],
            );
        }
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Creates an instance by hydrating public properties from a flat string map.
     *
     * This is the only intended way to instantiate a castable object. Each value in the map
     * is converted from its stored string form to the appropriate PHP type using the cast
     * declared on the corresponding property. Properties missing from the map retain their
     * declared PHP default values.
     *
     * ```php
     * $config = AiConfig::fromStringArray([
     *     'max_tokens' => '8192',
     *     'stream'     => '1',
     * ]);
     * ```
     *
     * @param array<string, null|string> $rawStringAttributes flat key → stored-string map
     */
    final public static function fromStringArray(array $rawStringAttributes): static
    {
        return new static($rawStringAttributes);
    }

    /**
     * Creates an instance from already-typed PHP values.
     *
     * Use this when values are already in their correct PHP types (e.g. from an in-memory
     * blueprint) and do not need string deserialisation. Unknown keys are ignored; missing
     * properties retain their declared PHP default values.
     *
     * Custom casters still receive `$this` as the parent object, consistent with
     * {@see fromStringArray()} — sibling-property reads will return PHP defaults for
     * properties not present in $typedValues.
     *
     * ```php
     * $config = AiConfig::fromArray(['max_tokens' => 8192, 'stream' => true]);
     * ```
     *
     * @param array<string, mixed> $typedValues property name → typed PHP value
     */
    final public static function fromArray(array $typedValues): static
    {
        return new static($typedValues, self::SOURCE_ARRAY);
    }

    /**
     * Serializes all public properties back to a flat string map suitable for database storage.
     *
     * Each PHP value is converted to its stored string form using the cast declared on the
     * property. Null values are preserved as null without attempting serialization.
     * Properties without a {@see CastedValue} annotation are cast to string via `(string)`.
     *
     * ```php
     * $row = $config->toArrayList();
     * // ['max_tokens' => '8192', 'stream' => '1', 'openai_api_key' => '<ciphertext>', ...]
     * ```
     *
     * @return array<string, null|string>
     */
    final public function toArrayList(): array
    {
        $result = [];

        foreach ($this->getPropertyReflections() as $name => $prop) {
            $value = $prop->isInitialized($this) ? $prop->getValue($this) : null;
            $result[$name] = null === $value ? null : $this->valueToString($name, $value);
        }

        return $result;
    }

    /**
     * Returns the cast map for this class, derived from {@see CastedValue} attributes on public properties.
     *
     * The result is cached statically per concrete class after the first call.
     *
     * @return array<string, CastedValue> property name → CastedValue DTO
     */
    final public function getCasts(): array
    {
        $class = static::class;

        if (isset(self::$castsCache[$class])) {
            return self::$castsCache[$class];
        }

        $casts = [];

        foreach ($this->getPropertyReflections() as $name => $prop) {
            $attrs = $prop->getAttributes(CastedValue::class);

            if ($attrs) {
                $casts[$name] = $attrs[0]->newInstance();
            } elseif (null !== ($inferred = CastedValue::tryFromProperty($prop))) {
                $casts[$name] = $inferred;
            }
        }

        return self::$castsCache[$class] = $casts;
    }

    /**
     * Converts a raw stored string to the PHP value for the given property.
     *
     * @param string $property the property name (used to look up the cast type)
     * @param string $stored the raw string value as read from the database
     *
     * @return mixed the PHP-typed value
     */
    private function valueFromString(string $property, string $stored): mixed
    {
        $cast = $this->getCasts()[$property] ?? null;
        if (null === $cast) {
            return $stored;
        }
        return self::getCasterInstance($cast->caster)->get($this, $stored);
    }

    /**
     * Converts a PHP property value to its stored string representation.
     *
     * @param string $property the property name (used to look up the cast type)
     * @param mixed $value the current PHP value of the property
     *
     * @return string the serialized string for database storage
     */
    private function valueToString(string $property, mixed $value): string
    {
        $cast = $this->getCasts()[$property] ?? null;
        if (null === $cast) {
            return (string)$value;
        }
        return self::getCasterInstance($cast->caster)->set($this, $value);
    }

    /**
     * Retrieves a CastsValue instance for the given ResolvedCaster, using the internal cache if available.
     *
     * @param ResolvedCaster $caster the resolved caster information (class + args)
     *
     * @return CastsValue the caster instance
     */
    private static function getCasterInstance(ResolvedCaster $caster): CastsValue
    {
        return self::$casterCache[(string)$caster] ??= new $caster->casterClass(...$caster->args);
    }

    /**
     * Retrieves ReflectionProperty instances for all public non-static properties of the concrete class.
     *
     * The result is cached statically per concrete class after the first call to avoid repeated reflection overhead.
     *
     * @return array<string, \ReflectionProperty> property name → ReflectionProperty instance
     */
    private function getPropertyReflections(): iterable
    {
        $class = static::class;

        if (isset(self::$propertyReflectionsCache[$class])) {
            return self::$propertyReflectionsCache[$class];
        }

        $props = [];

        foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }

            $props[$prop->getName()] = $prop;
        }

        return self::$propertyReflectionsCache[$class] = $props;
    }

    /**
     * Testing helper to clear all internal caches. Not intended for production use.
     * @return void
     */
    public static function reset(): void
    {
        self::$castsCache = [];
        self::$casterCache = [];
        self::$propertyReflectionsCache = [];
    }
}
