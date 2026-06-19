<?php
declare(strict_types=1);

namespace App\Casts\Exceptions;

use App\Casts\Contracts\CastableInstanceInterface;

/**
 * Thrown when the database or application value passed to an {@see \App\Casts\AsInstance} caster
 * is of an unexpected type or contains malformed data.
 */
class InvalidCastValueException extends \InvalidArgumentException implements CastExceptionInterface
{
    public static function forNonStringDatabaseValue(): self
    {
        return new self('Database value must be a JSON string, got a non-string value.');
    }

    public static function forInvalidJson(): self
    {
        return new self('Database value could not be decoded as JSON array.');
    }

    public static function forNonCastableInstance(mixed $value): self
    {
        return new self(sprintf(
            'Application value must be an instance of %s, got %s.',
            CastableInstanceInterface::class,
            get_debug_type($value)
        ));
    }
}
