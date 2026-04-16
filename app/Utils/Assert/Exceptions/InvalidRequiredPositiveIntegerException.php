<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exceptions;

class InvalidRequiredPositiveIntegerException extends AbstractAssertionException
{
    public static function forInvalidValue(mixed $value, ?string $key = null): self
    {
        return new self(sprintf(
                "Expected a positive integer%s, got %s",
                self::optionalKey($key),
                self::valueToString($value)
            )
        );
    }
}
