<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Exceptions;

class FormatterNotFoundException extends \RuntimeException implements FormatterExceptionInterface
{
    public static function forKey(string $key): self
    {
        return new self(\sprintf('No formatter is registered for format key "%s".', $key));
    }
}
