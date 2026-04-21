<?php

declare(strict_types=1);

namespace App\Utils\Casts;

use App\Utils\Casts\Casters\CastableObjectCaster;
use App\Utils\Casts\Casters\DateCaster;
use App\Utils\Casts\Casters\DefaultCaster;
use App\Utils\Casts\Casters\EncryptedCaster;
use App\Utils\Casts\Casters\EnumCaster;
use App\Utils\Casts\Contracts\BuiltInCasterInterface;
use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Exceptions\AmbiguousFormatArgumentException;
use App\Utils\Casts\Exceptions\InvalidCastTypeException;
use App\Utils\Casts\Values\CastType;
use App\Utils\Casts\Values\ResolvedCaster;

/**
 * Property attribute that declares the cast type (and optional format) for a public property
 * on an {@see AbstractCastableObject}.
 *
 * Accepts a {@see CastType} enum case or a plain string. Plain strings are split on the first
 * colon (`:`): the part before the colon is resolved to a {@see CastType} case (or left as a
 * class-name string for enums and custom casters), and the optional part after the colon becomes
 * the {@see $format} / argument string. An unrecognised base type that is neither a known
 * {@see CastType}, a PHP enum class, nor a {@see CastsValue} implementation throws an
 * {@see \InvalidArgumentException} immediately so typos surface at development time.
 *
 * ## Preferred usage — enum case (type-safe, IDE-friendly)
 *
 * ```php
 * #[CastedValue(CastType::DATETIME)]
 * public Carbon $created_at;
 *
 * #[CastedValue(CastType::IMMUTABLE_DATETIME)]
 * public CarbonImmutable $finished_at;
 *
 * // With a custom date format passed as second argument
 * #[CastedValue(CastType::DATETIME, 'd.m.Y H:i')]
 * public ?Carbon $formatted_at = null;
 *
 * // Encrypted: use the string shorthand (there is no CastType::ENCRYPTED case)
 * #[CastedValue('encrypted:string')]
 * public string $api_key = '';
 * ```
 *
 * ## Parameterised variants — plain string (colon-delimited shorthand)
 *
 * ```php
 * #[CastedValue('datetime:d.m.Y H:i')]       // type=Datetime, format='d.m.Y H:i'
 * public ?Carbon $formatted_at = null;
 *
 * #[CastedValue('encrypted:string')]          // type=Encrypted, format='string'
 * public string $api_key = '';
 * ```
 *
 * ## Custom caster class — plain string
 *
 * ```php
 * #[CastedValue(MoneyAmountCast::class)]
 * public Money $limit;
 *
 * #[CastedValue(MoneyAmountCast::class . ':USD')]   // format='USD' → passed as constructor arg
 * public Money $usd_reserve;
 * ```
 *
 * ## Built-in type strings (still accepted as plain strings for backwards compatibility)
 *
 * | Type string                        | PHP type on read         | Stored as              |
 * |------------------------------------|--------------------------|------------------------|
 * | `'int'` / `'integer'`              | `int`                    | numeric string         |
 * | `'float'` / `'double'` / `'real'`  | `float`                  | numeric string         |
 * | `'bool'` / `'boolean'`             | `bool`                   | `'1'` / `'0'`          |
 * | `'string'`                         | `string`                 | raw string             |
 * | `'array'` / `'json'`               | `array`                  | JSON string            |
 * | `'object'`                         | `stdClass`               | JSON string            |
 * | `'encrypted:string'`               | `string`                 | Laravel ciphertext     |
 * | `'encrypted:array'`                | `array`                  | encrypted JSON string  |
 * | `'encrypted:json'`                 | `array`                  | encrypted JSON string  |
 * | `'encrypted:object'`               | `stdClass`               | encrypted JSON string  |
 * | `'date'`                           | `Carbon`                 | `Y-m-d`                |
 * | `'immutable_date'`                 | `CarbonImmutable`        | `Y-m-d`                |
 * | `'datetime'`                       | `Carbon`                 | `Y-m-d H:i:s`          |
 * | `'datetime:FORMAT'`                | `Carbon`                 | given format           |
 * | `'immutable_datetime'`             | `CarbonImmutable`        | `Y-m-d H:i:s`          |
 * | `'immutable_datetime:FORMAT'`      | `CarbonImmutable`        | given format           |
 * | `'timestamp'`                      | `int`                    | Unix timestamp string  |
 * | Any `AbstractCastableObject` subclass FQN | Instance of that class | JSON object string     |
 *
 * @see CastType
 * @see AbstractCastableObject
 * @see CastsValue
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class CastedValue
{
    private const BUILT_IN_CASTERS = [
        DateCaster::class,
        EnumCaster::class,
        CastableObjectCaster::class,
        EncryptedCaster::class,
        DefaultCaster::class
    ];

    public ResolvedCaster $caster;

    /**
     * @param CastType|string|\Stringable $type A {@see CastType} enum case, a built-in type string
     *                                (optionally followed by `:format`), a fully-qualified
     *                                enum class name, or a fully-qualified {@see CastsValue}
     *                                class name (optionally followed by `:arg1,arg2`).
     * @param string|null $format Optional format / argument string. When $type is a plain
     *                                string the format is extracted automatically from after the
     *                                first colon; this parameter takes precedence when $type is
     *                                a {@see CastType} enum case.
     *
     * @throws \InvalidArgumentException when $type is a plain string whose base part does not
     *                                   resolve to a known {@see CastType}, a PHP enum class,
     *                                   or a {@see CastsValue} implementation.
     */
    public function __construct(CastType|string|\Stringable $type, ?string $format = null)
    {
        // Handle already resolved casters (Hacks itself through the gate by being a \Stringable)
        if ($type instanceof ResolvedCaster) {
            $this->caster = $type;
            return;
        }

        // Narrow the type so we strip \Stringable away.
        /* @var CastType|string $narrowedType */
        $narrowedType = $type instanceof \Stringable ? (string)$type : $type;

        // Extract format from type string if it's a plain string with a colon, otherwise use the provided $format argument
        if (is_string($narrowedType)) {
            $typeParts = explode(':', $narrowedType, 2);
            $baseType = $typeParts[0];
            $modifier = $typeParts[1] ?? null;
            if ($modifier !== null && $format !== null) {
                throw AmbiguousFormatArgumentException::forDuplicateFormat($baseType, $format);
            }
            $format = $modifier ?? $format;
            $narrowedType = $baseType;
            unset($typeParts, $modifier, $baseType);
        }

        // Try to resolve cast type from the narrowed type
        /* @var CastType|null $narrowedCastType */
        $narrowedCastType = $narrowedType instanceof CastType ? $narrowedType : CastType::tryFromString($narrowedType);
        // Theoretically the '' case should never happen, as if $narrowedType is not a CastType it should always be a string (but just to be safe)
        /* @var string $narrowedTypeString */
        $narrowedTypeString = is_string($narrowedType) ? $narrowedType : ($narrowedCastType->value ?? '');

        // Resolve the built-in casters
        foreach (self::BUILT_IN_CASTERS as $casterClass) {
            /* @var class-string<BuiltInCasterInterface> $casterClass */
            $args = $casterClass::argsForAttribute(
                $narrowedCastType,
                $narrowedTypeString,
                $format
            );
            if (is_array($args)) {
                $this->caster = new ResolvedCaster($casterClass, $args);
                return;
            }
        }

        if (class_exists($narrowedTypeString) && is_subclass_of($narrowedTypeString, CastsValue::class)) {
            $this->caster = new ResolvedCaster($narrowedTypeString, explode(',', $format ?? ''));
            return;
        }

        // Nope, something is wrong here...
        throw InvalidCastTypeException::forType($type);
    }

    /**
     * Try to create a CastedValue from a reflected property. Returns null if the property has no type
     * hint, otherwise tries to resolve a built-in caster based on the type hint and returns a CastedValue
     * with a ResolvedCaster for the built-in caster if successful, or throws if the type hint is uncastable or undetectable.
     *
     * @internal This method is not covered by the api contract! Do not use it
     */
    public static function tryFromProperty(\ReflectionProperty $prop): ?self
    {
        $type = $prop->getType();

        if (null === $type) {
            return null; // no type hint — raw string passthrough
        }

        if (!$type instanceof \ReflectionNamedType) {
            throw InvalidCastTypeException::forUndetectableTypeOfProp($prop);
        }

        foreach (self::BUILT_IN_CASTERS as $casterClass) {
            /* @var class-string<BuiltInCasterInterface> $casterClass */
            $args = $casterClass::argsForProperty($prop);
            if (is_array($args)) {
                return new self(new ResolvedCaster($casterClass, $args));
            }
        }

        throw InvalidCastTypeException::forUncastableTypeOfProp($prop);
    }
}
