<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use App\Services\ExternalContent\Exceptions\FailedToFetchUrlException;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Thin HTTP client shared across all ExternalContent proxy services.
 *
 * Every outbound request made by {@see FavIconProxy}, {@see ExternalImageProxy}, and
 * {@see WebsiteMetadataLoader} goes through this client so that SSRF protection and a
 * consistent bot User-Agent are applied uniformly.
 *
 * Usage:
 * ```php
 * $response = $proxyClient->fetchOrThrow('https://example.com/', timeout: 5);
 * echo $response->body();
 * ```
 *
 * @see FavIconProxy
 * @see ExternalImageProxy
 * @see WebsiteMetadataLoader
 */
#[Singleton]
readonly class ProxyClient
{
    public function __construct(
        private PendingRequest $http
    )
    {
    }

    /**
     * Perform an SSRF-safe GET request and return the response.
     *
     * Attaches a bot User-Agent and enforces SSRF protection via {@see PendingRequest::getSsrfSafe()},
     * which validates the target URL (and every redirect hop) against a public-IP allowlist before
     * opening any connection.
     *
     * @throws FailedToFetchUrlException when the HTTP response status is not 2xx.
     */
    public function fetchOrThrow(string $url, int $timeout = 5): Response
    {
        $response = $this->http
            ->timeout($timeout)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; HAWKI Link Preview Bot/1.0)',
            ])
            ->getSsrfSafe($url);

        if (!$response->successful()) {
            throw FailedToFetchUrlException::forUrl($url);
        }

        return $response;
    }
}
