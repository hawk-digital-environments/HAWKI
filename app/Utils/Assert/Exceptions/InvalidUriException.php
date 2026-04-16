<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exceptions;


class InvalidUriException extends AbstractAssertionException
{
    public static function forInvalidValue(mixed $value, ?string $key = null): self
    {
        $keyPart = $key !== null ? " for key '$key'" : '';
        $valueString = self::valueToString($value);
        return new self("Invalid URI$valueString$keyPart");
    }

    public static function forExceptionOfUriParsing(\Throwable $throwable, ?string $key = null): self
    {
        $keyPart = $key !== null ? " for key '$key'" : '';
        return new self("Failed to parse URI$keyPart: " . $throwable->getMessage(), previous: $throwable);
    }
}
