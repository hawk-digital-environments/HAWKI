<?php
declare(strict_types=1);

namespace App\Services\System\Http\Exceptions;

/**
 * Thrown when a URL is rejected by the SSRF guard before a connection is opened.
 *
 * Each static constructor covers one specific rejection reason so catch sites can
 * distinguish between a syntactically invalid URL and a valid-but-private-address URL.
 */
class SsrfBlockedException extends \RuntimeException implements HttpExceptionInterface
{
    /** Thrown when parse_url cannot extract a scheme and host from the given string. */
    public static function malformedUrl(string $url): self
    {
        return new self(sprintf('Malformed URL: "%s".', $url));
    }

    public static function unsupportedScheme(string $scheme): self
    {
        return new self(sprintf('Only http and https URLs are allowed, got: "%s".', $scheme));
    }

    public static function credentialsInUrl(): self
    {
        return new self('Credentials in URL are not allowed.');
    }

    public static function nonPublicAddress(string $host): self
    {
        return new self(sprintf('URL host "%s" resolves to a non-public address.', $host));
    }

    public static function unresolvableHost(string $host): self
    {
        return new self(sprintf('Could not resolve host: "%s".', $host));
    }
}
