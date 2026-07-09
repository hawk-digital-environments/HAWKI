<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Exceptions;

/**
 * Thrown by {@see ProxyClient} when an outbound HTTP request returns a non-2xx status code.
 */
class FailedToFetchUrlException extends \RuntimeException implements ExternalContentExceptionInterface
{
    /**
     * Create an exception for a URL that returned a non-2xx HTTP response.
     */
    public static function forUrl(string $url): self
    {
        return new self(sprintf('Failed to fetch external URL "%s".', $url));
    }
}
