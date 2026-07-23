<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ClientClassNotRegisteredException extends \RuntimeException implements AiExceptionInterface
{
    public static function forClientClass(string $clientClass): self
    {
        return new self(sprintf(
            'Client class "%s" is not registered in the AI client registry.',
            $clientClass
        ));
    }
}
