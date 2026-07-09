<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ProviderHasNoClientClassException extends \RuntimeException implements AiExceptionInterface
{
    public static function forProvider(string $providerId): self
    {
        return new self(sprintf(
            'Provider "%s" does not have a client class specified.',
            $providerId
        ));
    }
}
