<?php
declare(strict_types=1);


namespace App\Services\System\Http;

use App\Services\System\Http\Exceptions\InvalidBaseUrlException;

/**
 * Resolves potentially relative URL references against a base URL.
 *
 * Used in two places: resolving HTTP redirect {@code Location} headers (which may be
 * relative) and turning partial URLs found in scraped HTML (favicons, images) into
 * absolute form so they can be fetched or displayed.
 */
class UrlResolver
{
    /**
     * Resolve {@param $relativeUrl} against {@param $baseUrl}.
     *
     * Resolution priority:
     *   1. Already-absolute URL — returned unchanged.
     *   2. Protocol-relative (`//…`) — base scheme prepended.
     *   3. Absolute path (`/…`) — base origin (scheme + host + port) prepended.
     *   4. Relative path — joined to the directory portion of the base path.
     */
    public static function resolve(string $baseUrl, string $relativeUrl): string
    {
        // If already absolute, return as is — base URL is not needed.
        if (filter_var($relativeUrl, FILTER_VALIDATE_URL)) {
            return $relativeUrl;
        }

        $base = parse_url($baseUrl);

        if ($base === false || empty($base['host'])) {
            throw InvalidBaseUrlException::forBaseUrl($baseUrl);
        }

        $scheme = $base['scheme'] ?? 'https';

        // Handle protocol-relative URLs
        if (str_starts_with($relativeUrl, '//')) {
            return $scheme . ':' . $relativeUrl;
        }

        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':' . $base['port'] : '';
        $baseUri = $scheme . '://' . $host . $port;

        // Handle absolute paths
        if (str_starts_with($relativeUrl, '/')) {
            return $baseUri . $relativeUrl;
        }

        // Handle relative paths
        $basePath = dirname($base['path'] ?? '/');
        $basePath = $basePath === '/' ? '' : $basePath; // Avoid double slashes
        return $baseUri . $basePath . '/' . $relativeUrl;
    }
}
