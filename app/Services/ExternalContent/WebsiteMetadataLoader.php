<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use App\Services\ExternalContent\Events\ResolvingWebsiteMetadataFilterEvent;
use App\Services\ExternalContent\Events\WebsiteMetadataResolvedFilterEvent;
use App\Services\ExternalContent\Values\WebsiteMetadata;
use App\Services\System\Http\UrlResolver;
use App\Services\System\Time\Clock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

readonly class WebsiteMetadataLoader
{
    public function __construct(
        private UrlGenerator    $urlGenerator,
        private ProxyClient     $client,
        private Repository      $cache,
        private LoggerInterface $logger,
        private Clock           $clock = new Clock()
    )
    {
    }

    public function load(string $url): WebsiteMetadata
    {
        return $this->cache->remember(
            key: 'website_metadata:' . md5($url) . '-' . now()->format('Y-m-d-H-s'),
            ttl: $this->clock->now()->addHours(24),
            callback: function () use ($url): WebsiteMetadata {
                try {
                    $preEvent = ResolvingWebsiteMetadataFilterEvent::dispatch($url);
                    if ($preEvent->getResolved() !== null) {
                        return $preEvent->getResolved();
                    }

                    $response = $this->client->fetchOrThrow($url, 5);

                    $data = $this->extractMetadata($url, $response->body());
                } catch (\Throwable $e) {
                    $this->logger->warning("Error fetching metadata from URL: $url. Error: " . $e->getMessage(), ['exception' => $e]);
                    $data = $this->createFallbackMetadata($url);
                }

                return WebsiteMetadataResolvedFilterEvent::dispatch($url, $data)->getData();
            }
        );
    }

    private function extractMetadata(string $url, string $body): WebsiteMetadata
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($body);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        return new WebsiteMetadata(
            url: $url,
            domain: parse_url($url, PHP_URL_HOST) ?? $url,
            title: $this->extractTitle($xpath, $url),
            description: $this->extractDescription($xpath),
            image: $this->extractPreviewImageUrl($xpath, $url),
            favicon: $this->extractFaviconUrl($xpath, $url)
        );
    }

    private function extractTitle(\DOMXPath $xpath, string $url): ?string
    {
        // Try to get the title from the <title> tag
        $nodes = $xpath->query("//title");
        if ($nodes->length > 0) {
            return $nodes->item(0)->textContent;
        }

        // Fallback to Open Graph title
        $nodes = $xpath->query("//meta[@property='og:title']");
        if ($nodes->length > 0) {
            return $nodes->item(0)->getAttribute('content');
        }

        // Fallback to Twitter Card title
        $nodes = $xpath->query("//meta[@name='twitter:title']");
        if ($nodes->length > 0) {
            return $nodes->item(0)->getAttribute('content');
        }

        return $this->createFallbackTitle($url);
    }

    private function extractDescription(\DOMXPath $xpath): ?string
    {
        // Try to get the description from the Open Graph meta tag
        $nodes = $xpath->query("//meta[@property='og:description']");
        if ($nodes->length > 0) {
            return $nodes->item(0)->getAttribute('content');
        }

        // Fallback to Twitter Card description
        $nodes = $xpath->query("//meta[@name='twitter:description']");
        if ($nodes->length > 0) {
            return $nodes->item(0)->getAttribute('content');
        }

        // Fallback to standard meta description
        $nodes = $xpath->query("//meta[@name='description']");
        if ($nodes->length > 0) {
            return $nodes->item(0)->getAttribute('content');
        }

        return null;
    }

    private function extractPreviewImageUrl(\DOMXPath $xpath, string $url): ?string
    {
        // Try to get the image from the Open Graph meta tag
        $nodes = $xpath->query("//meta[@property='og:image']");
        if ($nodes->length > 0) {
            $imageUrl = $nodes->item(0)->getAttribute('content');
            return $this->createPreviewImageUrl(UrlResolver::resolve($url, $imageUrl));
        }

        // Fallback to Twitter Card image
        $nodes = $xpath->query("//meta[@name='twitter:image']");
        if ($nodes->length > 0) {
            $imageUrl = $nodes->item(0)->getAttribute('content');
            return $this->createPreviewImageUrl(UrlResolver::resolve($url, $imageUrl));
        }

        return $this->createPreviewImageUrl($url, true);
    }

    private function extractFaviconUrl(\DOMXPath $xpath, string $url): ?string
    {
        // Try to get the favicon from the <link rel="icon"> tag
        $nodes = $xpath->query("//link[@rel='icon']");
        if ($nodes->length > 0) {
            $faviconUrl = $nodes->item(0)->getAttribute('href');
            return $this->createFaviconUrl(UrlResolver::resolve($url, $faviconUrl));
        }

        // Fallback to <link rel="shortcut icon">
        $nodes = $xpath->query("//link[@rel='shortcut icon']");
        if ($nodes->length > 0) {
            $faviconUrl = $nodes->item(0)->getAttribute('href');
            return $this->createFaviconUrl(UrlResolver::resolve($url, $faviconUrl));
        }

        return $this->createFaviconUrl($url);
    }

    private function createFaviconUrl(string $url): string
    {
        return $this->urlGenerator->route(
            'api.link-preview.favicon',
            ['url' => 'https://' . $this->getDomainFromUrl($url)]);
    }

    private function createPreviewImageUrl(string $url, bool $isFallback = false): string
    {
        $imageIdentifier = $isFallback ? 'fallback_' . md5($url) : $url;
        return $this->urlGenerator->route(
            'api.link-preview.image',
            ['url' => $imageIdentifier]);
    }

    private function createFallbackMetadata(string $url): WebsiteMetadata
    {
        return new WebsiteMetadata(
            url: $url,
            domain: $this->getdomainFromUrl($url),
            title: $this->createFallbackTitle($url),
            description: null,
            image: $this->createPreviewImageUrl($url, true),
            favicon: $this->createFaviconUrl($url),
            isFallback: true
        );
    }

    private function createFallbackTitle(string $url): string
    {
        // Remove www prefix, and all tdl suffixes (e.g. .com, .co.uk, .org, etc.) from the domain
        $domainWithoutWww = preg_replace('/^www\./', '', $this->getDomainFromUrl($url));
        $domainWithoutTld = preg_replace('/\.[^.]+$/', '', $domainWithoutWww);
        return Str::headline($domainWithoutTld);
    }

    private function getDomainFromUrl(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?? $url;
    }
}
