<?php
declare(strict_types=1);


namespace App\Utils\Casts\Contracts;


use App\Utils\Casts\CastedValue;
use App\Utils\Casts\Values\CastType;

/**
 * @internal Do not use this interface directly, it's only for built-in casters to implement. It may change without deprecation in future versions.
 */
interface BuiltInCasterInterface
{
    /**
     * Helper method to get the constructor attributes of the caster based on using {@see CastedValue} as attribute on a property.
     * The attribute will already preprocess the type string (e.g. 'int' will be resolved to CastType::INT) and pass it
     * as the first argument, and the second argument is the type as a string (or the class name if a cast type couldn't be resolved, e.g. for enums and custom casters),
     * and the optional format string (e.g. for date formats or encrypted sub-types). The last value will automatically
     * be extracted from either the type string (if using the colon-delimited shorthand) or the second argument of the attribute, so both of these are supported.
     * Logistically this method is used to determine if a property with a given attribute should be casted using this caster,
     * and if so, what arguments should be passed to the caster's constructor when instantiating it for that property.
     */
    public static function argsForAttribute(CastType|null $type, string $typeString, ?string $format): array|null;

    /**
     * Similar to {@see argsForAttribute}, but for properties that should be implicitly casted (e.g. no {@see CastedValue} attribute,
     * but the type is set to \DateTimeInterface, so it should be casted using the DateTimeCaster).
     * Logistically this method is used to determine if a property with a given type should be casted using this caster,
     * and if so, what arguments should be passed to the caster's constructor when instantiating it for that property.
     */
    public static function argsForProperty(\ReflectionProperty $prop): array|null;
}
