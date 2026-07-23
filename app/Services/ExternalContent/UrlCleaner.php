<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Illuminate\Container\Attributes\Singleton;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves and sanitises external URLs by following redirect chains and stripping
 * common analytics tracking parameters.
 *
 * URLs are cleaned in two passes:
 *  1. Tracking parameters (UTM tags, {@code gclid}, {@code fbclid}) are stripped from the
 *     input URL immediately, before any HTTP request is made.
 *  2. An async HEAD request follows up to five redirect hops; each redirect destination also
 *     has tracking parameters stripped via the {@code on_redirect} callback.
 *
 * Multiple URLs are resolved concurrently using Guzzle async promises, so cleaning a batch
 * of citation links does not serialise the HTTP round-trips.
 *
 * Usage:
 * ```php
 * // Single URL — follows redirects and strips tracking params:
 * $clean = $urlCleaner->clean('https://t.co/abc?utm_source=twitter');
 *
 * // Batch — all requests run concurrently:
 * $cleaned = $urlCleaner->cleanMany([
 *     'https://bit.ly/xyz?fbclid=123',
 *     'https://example.com/?utm_medium=email',
 * ]);
 * ```
 *
 * @see CitationUrlCleaner for a higher-level wrapper that cleans {@see UrlCitation} objects.
 */
#[Singleton]
readonly class UrlCleaner
{
    private Client $client;

    public function __construct(
        private LoggerInterface $logger,
        Client                  $client = null
    )
    {
        $this->client = $client ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    /**
     * Clean a single URL by following its redirect chain and stripping tracking parameters.
     *
     * Delegates to {@see cleanMany()} — see that method for full semantics and failure behaviour.
     */
    public function clean(string $url): string
    {
        return $this->cleanMany([$url])[0];
    }

    /**
     * Resolve redirect chains for multiple URLs concurrently and strip tracking parameters.
     *
     * All HEAD requests run in parallel via Guzzle promises. When a request fails, a warning
     * is logged and the cleaned input URL (tracking params stripped, no redirect followed) is
     * returned for that entry. URLs are returned in the same order as the input array.
     *
     * @param string[] $urls
     * @return string[]
     */
    public function cleanMany(array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        $finalUrls = array_map(
            fn(string $url) => (string)$this->removeTrackingParameters(\GuzzleHttp\Psr7\Utils::uriFor($url)),
            $urls
        );
        $promises = [];

        foreach ($urls as $key => $url) {
            $promises[$key] = $this->client->requestAsync('HEAD', $url, [
                'allow_redirects' => [
                    'max' => 5,
                    // on_redirect fires for each hop and receives the fully-resolved URI
                    // of the NEXT request, so the last call gives us the final destination.
                    'on_redirect' => function ($request, $response, UriInterface $uri) use ($key, &$finalUrls): void {
                        $finalUrls[$key] = (string)$this->removeTrackingParameters($uri);
                    },
                ],
            ]);
        }

        foreach (Utils::settle($promises)->wait() as $key => $result) {
            if ($result['state'] !== 'fulfilled') {
                $this->logger->warning('UrlCleaner: failed to resolve URL redirect chain', [
                    'url' => $urls[$key],
                    'reason' => (string)($result['reason'] ?? 'unknown'),
                ]);
            }
        }

        return $finalUrls;
    }

    /**
     * Strip well-known analytics tracking parameters from a URI's query string.
     *
     * Removed parameters: {@code utm_source}, {@code utm_medium}, {@code utm_campaign},
     * {@code utm_term}, {@code utm_content}, {@code gclid}, {@code fbclid}.
     * All other query parameters are preserved.
     */
    private function removeTrackingParameters(UriInterface $uri): UriInterface
    {
        $queryParams = [];
        parse_str($uri->getQuery(), $queryParams);

        // Remove common tracking parameters
        $trackingParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];
        foreach ($trackingParams as $param) {
            unset($queryParams[$param]);
        }

        // Rebuild the query string without tracking parameters
        $newQuery = http_build_query($queryParams);

        return $uri->withQuery($newQuery);
    }
}
