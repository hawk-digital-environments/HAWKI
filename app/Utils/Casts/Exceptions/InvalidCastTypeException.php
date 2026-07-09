<?php
declare(strict_types=1);


namespace App\Utils\Casts\Exceptions;


use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Values\CastType;
use ReflectionType;

/**
 * @api
 */
class InvalidCastTypeException extends \InvalidArgumentException implements CastableObjectExceptionInterface
{
    public static function forType(CastType|string $type): self
    {
        return new self(\sprintf(
            '"%s" is not a valid cast type. Expected a CastType enum case, a PHP enum class name, or a fully-qualified %s implementation.',
            $type instanceof CastType ? $type->value : $type,
            CastsValue::class,
        ));
    }

    public static function forInvalidEncryptedType(string $givenType, array $encryptableTypes): self
    {
        return new self(sprintf(
            "Invalid encrypted cast type: %s. Valid types are: %s",
            $givenType,
            implode(', ', $encryptableTypes)
        ));
    }

    public static function forUndetectableTypeOfProp(\ReflectionProperty $prop): self
    {
        return new self(\sprintf(
            '%s::$%s has a union/intersection type and requires an explicit #[CastedValue] annotation.',
            $prop->class,
            $prop->getName(),
        ));
    }

    public static function forUncastableTypeOfProp(\ReflectionProperty $prop): self
    {
        return new self(\sprintf(
            '%s::$%s has type "%s" which cannot be cast automatically. Add a #[CastedValue] annotation.',
            $prop->class,
            $prop->getName(),
            self::propertyTypeToString($prop)
        ));
    }

    private static function propertyTypeToString(\ReflectionProperty $prop): string
    {
        $recursiveWalker = static function (ReflectionType $type) use (&$recursiveWalker): string {
            if ($type instanceof \ReflectionNamedType) {
                return $type->getName();
            }
            if ($type instanceof \ReflectionUnionType) {
                $typeNames = array_map($recursiveWalker, $type->getTypes());
                sort($typeNames);
                return implode('|', $typeNames);
            }
            if ($type instanceof \ReflectionIntersectionType) {
                $typeNames = array_map($recursiveWalker, $type->getTypes());
                sort($typeNames);
                return implode('&', $typeNames);
            }
            // @codeCoverageIgnoreStart
            // This should never happen, as the only possible ReflectionType implementations are the ones handled above, future proof
            return 'unknown';
            // @codeCoverageIgnoreEnd
        };

        $type = $prop->getType();
        if ($type === null) {
            return 'none';
        }

        return $recursiveWalker($type);
    }
}
