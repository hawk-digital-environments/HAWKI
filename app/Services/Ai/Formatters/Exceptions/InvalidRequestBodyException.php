<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

class InvalidRequestBodyException extends FormatterRequestException
{
    public static function forUnparseableBody(): self
    {
        return new self(
            'The request body could not be parsed as a JSON object.',
            errorCode: 'invalid_request_body',
        );
    }

    public static function forMissingInput(): self
    {
        return new self(
            'The request is missing the required "input" field.',
            errorCode: 'missing_input',
            param: 'input',
        );
    }
}
