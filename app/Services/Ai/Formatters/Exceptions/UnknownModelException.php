<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

use App\Services\Ai\Exceptions\ModelIdNotAvailableException;

/**
 * The requested model does not exist — mapped onto the OpenAI-style 404 model error.
 */
class UnknownModelException extends FormatterRequestException
{
    public static function fromModelException(ModelIdNotAvailableException $exception): self
    {
        return new self(
            $exception->getMessage(),
            errorCode: 'model_not_found',
            httpStatus: 404,
            errorType: 'invalid_request_error',
            param: 'model',
        );
    }
}
