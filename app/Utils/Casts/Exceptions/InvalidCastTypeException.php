<?php
declare(strict_types=1);


namespace App\Utils\Casts\Exceptions;


use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Values\CastType;

class InvalidCastTypeException extends \InvalidArgumentException
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
            $prop->getType()?->getName(),
        ));
    }
}
