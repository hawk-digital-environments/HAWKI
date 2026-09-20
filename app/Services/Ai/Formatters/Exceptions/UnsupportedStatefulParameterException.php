<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

/**
 * The request uses a stateful feature that the stateless HAWKI proxy does not support.
 */
class UnsupportedStatefulParameterException extends FormatterRequestException
{
    public static function forStore(): self
    {
        return new self(
            'HAWKI is a stateless proxy; store is not supported. Send store: false and the full conversation history with each request.',
            errorCode: 'store_not_supported',
        );
    }

    public static function forPreviousResponseId(): self
    {
        return new self(
            'HAWKI is a stateless proxy; previous_response_id is not supported. Send the full conversation history in input.',
            errorCode: 'previous_response_not_found',
            httpStatus: 404,
        );
    }
}
