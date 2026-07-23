<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ModelIdNotAvailableException extends \RuntimeException implements AiExceptionInterface
{
    public static function forModelId(string|int $modelId): self
    {
        return new self(sprintf('The model with ID "%s" is not available.', $modelId));
    }
}
