<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exceptions;


class InvalidArrayOfTypesException extends AbstractAssertionException
{
    public static function forNonArrayValue(
        mixed   $value,
        string  $allowedType,
        ?string $key = null,
    ): self
    {
        return new self(
            sprintf(
                "Expected an array of %s%s, got %s",
                $allowedType,
                self::optionalKey($key),
                self::valueToString($value)
            )
        );
    }

    public static function forInvalidItem(
        array      $value,
        int|string $index,
        string     $allowedType,
        ?string    $key = null,
    ): self
    {
        return new self(
            sprintf(
                "Expected all items in the array to be of type %s%s, but item at index '%s' is of type %s",
                $allowedType,
                self::optionalKey($key),
                $index,
                self::valueToString($value[$index] ?? null)
            )
        );
    }
}
