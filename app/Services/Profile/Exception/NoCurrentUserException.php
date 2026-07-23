<?php
declare(strict_types=1);


namespace App\Services\Profile\Exception;


class NoCurrentUserException extends \RuntimeException implements ProfileExceptionInterface
{
    public static function forMethod(string $method): self
    {
        return new self(
            sprintf(
                'Failed to execute method %s, because it requires a currently authenticated user.',
                $method
            )
        );
    }
}
