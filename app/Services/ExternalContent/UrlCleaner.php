<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Illuminate\Container\Attributes\Singleton;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

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

    public function clean(string $url): string
    {
        return $this->cleanMany([$url])[0];
    }

    /**
     * Resolves redirect chains for multiple URLs concurrently.
     * Returns resolved URLs in the same order as the input array.
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
