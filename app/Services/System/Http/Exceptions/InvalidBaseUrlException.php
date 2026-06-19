<?php
declare(strict_types=1);

namespace App\Services\System\Http\Exceptions;

/**
 * Thrown by {@see \App\Services\System\Http\UrlResolver} when the base URL is not a valid
 * absolute URL and resolution cannot proceed.
 */
class InvalidBaseUrlException extends \RuntimeException implements HttpExceptionInterface
{
    public static function forBaseUrl(string $baseUrl): self
    {
        return new self(sprintf('Base URL "%s" is not a valid absolute URL.', $baseUrl));
    }
}
