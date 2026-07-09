<?php
declare(strict_types=1);

namespace App\Services\System\Http\Exceptions;

/**
 * Thrown when the configured redirect limit is exhausted before receiving a non-redirect response.
 *
 * The limit is read from the Guzzle {@code allow_redirects.max} option on the originating
 * {@see PendingRequest}; it defaults to 5 when not set.
 */
class TooManyRedirectsException extends \RuntimeException implements HttpExceptionInterface
{
    /** @param $maxRedirects The limit that was reached, as configured on the originating request. */
    public static function forUrl(string $url, int $maxRedirects): self
    {
        return new self(sprintf('Failed to fetch "%s" after %d redirects.', $url, $maxRedirects));
    }
}
