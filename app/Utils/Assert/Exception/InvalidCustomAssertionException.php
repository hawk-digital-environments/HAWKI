<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;


class InvalidCustomAssertionException extends AbstractAssertionException
{
    public static function fromCustomMessage(string $message, mixed $value, ?string $key = null): self
    {
        return new self(
            sprintf(
                'Invalid value "%s"%s: %s',
                self::valueToString($value),
                self::optionalKey($key),
                $message
            )
        );
    }

    public static function fromThrownException(\Throwable $throwable, mixed $value, ?string $key = null): self
    {
        return new self(
            sprintf(
                'Invalid value "%s"%s: %s',
                self::valueToString($value),
                self::optionalKey($key),
                $throwable->getMessage()
            ),
            previous: $throwable
        );
    }
}
