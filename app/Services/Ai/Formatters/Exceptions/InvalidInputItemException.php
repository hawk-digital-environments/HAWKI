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
}
