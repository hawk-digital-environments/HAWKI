<?php
declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;

class InvalidProviderAdapterException extends \RuntimeException implements AiExceptionInterface
{
    public static function forClassNotImplementingInterface(
        string $adapterKey,
        string $providerClass,
        string $actualClass
    ): self
    {
        return new self(sprintf(
            'Expected provider class %s to implement %s, but got instance of %s for provider adapter with key %s.',
            $providerClass,
            ProviderAdapterInterface::class,
            $actualClass,
            $adapterKey
        ));
    }
}
