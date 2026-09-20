<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

/**
 * The request named an explicit `{format}` segment no formatter is registered under.
 *
 * Rendered as a 400 — a typo'd format must surface, not silently serve the default
 * formatter's shape (D10 resolution; the lenient fallback is only correct while a
 * single format exists).
 */
class UnknownFormatException extends FormatterRequestException
{
    public static function forKey(string $key): self
    {
        return new self(
            \sprintf('Unknown format "%s".', $key),
            errorCode: 'unknown_format',
            param: 'format',
        );
    }
}
