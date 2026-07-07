<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ModelListResponseException extends \RuntimeException implements AiExceptionInterface
{
    public static function forInvalidJson(string $body, \Throwable $previous): self
    {
        return new self(
            sprintf('Failed to parse model list response as JSON: %s', $body),
            0,
            $previous
        );
    }

    public static function forNonArrayResponse(string $type): self
    {
        return new self(sprintf('Model list response is not an array, got %s.', $type));
    }

    public static function forNonArrayExtract(string $path, mixed $value): self
    {
        return new self(sprintf(
            'Extracted data at path "%s" is not an array, got: %s',
            $path,
            json_encode($value)
        ));
    }
}
