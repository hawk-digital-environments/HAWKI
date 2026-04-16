<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exceptions;


class ValueIsNotInListException extends AbstractAssertionException
{
    public static function forInvalidValue(mixed $value, array $list, ?string $key = null): self
    {
        $listString = implode(', ', array_map(fn($item) => is_string($item) ? "\"$item\"" : (string)$item, $list));
        return new self(sprintf(
                "Expected a value from the list [%s]%s, got %s",
                $listString,
                self::optionalKey($key),
                self::valueToString($value)
            )
        );
    }
}
