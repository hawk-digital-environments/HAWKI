<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ProviderAdapterNotFoundException extends \RuntimeException implements AiExceptionInterface
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Provider adapter with key "%s" is not registered.', $key));
    }
}
