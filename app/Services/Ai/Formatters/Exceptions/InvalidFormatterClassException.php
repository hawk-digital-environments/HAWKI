<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

class InvalidFormatterClassException extends \RuntimeException implements FormatterExceptionInterface
{
    public static function forClassNotImplementingInterface(string $key, string $formatterClass): self
    {
        return new self(\sprintf(
            'The class "%s" registered for format key "%s" does not implement FormatterInterface.',
            $formatterClass,
            $key,
        ));
    }
}
