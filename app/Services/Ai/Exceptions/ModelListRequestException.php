<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ModelListRequestException extends \RuntimeException implements AiExceptionInterface
{
    public static function forConnectionFailure(string $url, \Throwable $previous): self
    {
        return new self(
            sprintf('Failed to connect to model list endpoint "%s": %s', $url, $previous->getMessage()),
            0,
            $previous
        );
    }

    public static function forUnsuccessfulResponse(string $url, string $body): self
    {
        return new self(sprintf('Model list request to "%s" returned a non-successful response: %s', $url, $body));
    }
}
