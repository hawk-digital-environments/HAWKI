<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ProviderAdapterAlreadyRegisteredException extends \RuntimeException implements AiExceptionInterface
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Provider adapter with key "%s" is already registered.', $key));
    }
}
