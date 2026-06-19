<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class InvalidModelCapabilityException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forUndeclaredKey(string $key): self
    {
        return new self(sprintf('Capability "%s" is not declared in the ModelCapabilityRegistry.', $key));
    }
}
