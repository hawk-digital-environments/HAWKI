<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

use Laravel\Ai\Exceptions\NoSuchToolException;

/**
 * The model called a tool that is neither registered server-side nor declared by the
 * client — surfaced as a clean wire-format error instead of an internal failure.
 */
class UnknownToolCallException extends FormatterRequestException
{
    public static function fromVendorException(NoSuchToolException $exception): self
    {
        return new self(
            $exception->getMessage(),
            errorCode: 'unknown_tool',
            param: 'tools',
        );
    }
}
