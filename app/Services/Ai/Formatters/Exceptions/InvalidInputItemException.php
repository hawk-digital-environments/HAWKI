<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

class InvalidInputItemException extends FormatterRequestException
{
    public static function forMalformedItem(mixed $item): self
    {
        return new self(
            \sprintf(
                'An input item is malformed: expected an object, got %s.',
                get_debug_type($item),
            ),
            errorCode: 'invalid_input_item',
            param: 'input',
        );
    }

    public static function forUnknownType(string $type): self
    {
        return new self(
            \sprintf('The input item type "%s" is not supported by this endpoint.', $type),
            errorCode: 'unsupported_input_item_type',
            param: 'input',
        );
    }

    public static function forMissingTrailingUserMessage(): self
    {
        return new self(
            'The input must end with a user message or a function_call_output (the continuation turn of a client-driven tool loop); a trailing bare function_call or assistant message cannot be continued.',
            errorCode: 'missing_user_turn',
            param: 'input',
        );
    }

    public static function forUnsupportedSchemaRoot(mixed $rootType): self
    {
        return new self(
            \sprintf(
                'The json_schema response format must be rooted at an object (got type "%s"); only object-rooted schemas are supported.',
                \is_string($rootType) ? $rootType : get_debug_type($rootType),
            ),
            errorCode: 'unsupported_response_format',
            param: 'text.format',
        );
    }

    public static function forStreamingWithStructuredOutput(): self
    {
        return new self(
            'Streaming structured output (json_schema) is not supported; request a non-streaming response or use json_object.',
            errorCode: 'unsupported_response_format',
            param: 'stream',
        );
    }
}
