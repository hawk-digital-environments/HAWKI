<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class InvalidProviderSettingsOperationException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forInvalidInstanceValue(string $key, string $expectedClass): self
    {
        return new self("Value for $key must be an instance of $expectedClass");
    }

    public static function forRequiredInstanceKey(string $key): self
    {
        return new self("Cannot remove $key as it is a required instance key.");
    }
}
