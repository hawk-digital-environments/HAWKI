<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;

class InvalidRequiredNonNegativeIntegerException extends AbstractAssertionException
{
    public static function forInvalidValue(mixed $value, ?string $key = null): self
    {
        return new self(sprintf(
                "Expected a non-negative integer%s, got %s",
                self::optionalKey($key),
                self::valueToString($value)
            )
        );
    }
}
