<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use App\Services\ExternalContent\Events\ResolvingWebsiteMetadataFilterEvent;
use App\Services\ExternalContent\Events\WebsiteMetadataResolvedFilterEvent;
use App\Services\ExternalContent\Values\WebsiteMetadata;
use App\Services\System\Http\UrlResolver;
use App\Services\System\Time\CarbonClock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Fetches a webpage and extracts Open Graph / Twitter Card metadata for link preview cards.
 *
 * For each metadata field the following fallback chain is applied:
 *  - **Title**: {@code <title>} → {@code og:title} → {@code twitter:title} → domain-derived label
 *  - **Description**: {@code og:description} → {@code twitter:description} → {@code meta[name=description]}
 *  - **Preview image**: {@code og:image} → {@code twitter:image} → URL-based fallback gradient
 *  - **Favicon**: {@code link[rel=icon]} → {@code link[rel="shortcut icon"]} → domain-based proxy URL
 *
 * Image and favicon values in the returned {@see WebsiteMetadata} are always internal HAWKI
 * proxy URLs (pointing at {@see ExternalImageProxy} and {@see FavIconProxy} respectively),
 * so the browser never contacts third-party image servers directly.
 *
 * Resolved metadata is cached for 24 hours. Both the fetch and the parsed result can be
 * intercepted through filter events.
 *
 * Usage:
 * ```php
 * $meta = $websiteMetadataLoader->load('https://example.com/article');
 * // $meta->title, $meta->description, $meta->image (proxy URL), $meta->favicon (proxy URL)
 * return response()->json($meta);
 * ```
 *
 * @see ResolvingWebsiteMetadataFilterEvent  dispatched before the fetch; listeners can short-circuit
 * @see WebsiteMetadataResolvedFilterEvent   dispatched after parsing; listeners can enrich or override
 */
readonly class WebsiteMetadataLoader
{
    public function __construct(
        private UrlGenerator    $urlGenerator,
        private ProxyClient     $client,
        private Repository      $cache,
        private LoggerInterface $logger,
        private CarbonClock     $clock = new CarbonClock()
    )
    {
    }

    /**
     * Fetch and cache metadata for the given URL, returning a populated {@see WebsiteMetadata}.
     *
     * Cached for 24 hours. If the fetch or parsing fails, a fallback metadata object is returned
     * with the domain and a derived title so the UI always has something to display.
     */
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

    /**
     * Parse the HTML body and assemble a {@see WebsiteMetadata} value object.
     *
     * HTML parsing is intentionally lenient — {@see \DOMDocument::loadHTML()} with libxml error
     * suppression tolerates the malformed markup found on most real-world pages.
     */
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

    /**
     * Extract the page title, trying {@code <title>}, {@code og:title}, {@code twitter:title}
     * in order, then falling back to a domain-derived label.
     */
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
            return $this->getDomElement($nodes->item(0))?->getAttribute('content');
        }

        // Fallback to Twitter Card title
        $nodes = $xpath->query("//meta[@name='twitter:title']");
        if ($nodes->length > 0) {
            return $this->getDomElement($nodes->item(0))?->getAttribute('content');
        }

        return $this->createFallbackTitle($url);
    }

    /**
     * Extract the page description, trying {@code og:description}, {@code twitter:description},
     * {@code meta[name=description]} in order. Returns null when no description tag is found.
     */
    private function extractDescription(\DOMXPath $xpath): ?string
    {
        // Try to get the description from the Open Graph meta tag
        $nodes = $xpath->query("//meta[@property='og:description']");
        if ($nodes->length > 0) {
            return $this->getDomElement($nodes->item(0))?->getAttribute('content');
        }

        // Fallback to Twitter Card description
        $nodes = $xpath->query("//meta[@name='twitter:description']");
        if ($nodes->length > 0) {
            return $this->getDomElement($nodes->item(0))?->getAttribute('content');
        }

        // Fallback to standard meta description
        $nodes = $xpath->query("//meta[@name='description']");
        if ($nodes->length > 0) {
            return $this->getDomElement($nodes->item(0))?->getAttribute('content');
        }

        return null;
    }

    /**
     * Extract the preview image URL, trying {@code og:image} then {@code twitter:image}.
     *
     * The raw URL from the HTML (which may be relative) is resolved to an absolute URL via
     * {@see UrlResolver::resolve()} and wrapped in a HAWKI image proxy route so the browser
     * fetches through {@see ExternalImageProxy}. Falls back to a fallback proxy URL keyed
     * by the page URL when no image tag is found.
     */
    private function extractPreviewImageUrl(\DOMXPath $xpath, string $url): string
    {
        // Try to get the image from the Open Graph meta tag
        $nodes = $xpath->query("//meta[@property='og:image']");
        if ($nodes->length > 0) {
            $imageUrl = $this->getDomElement($nodes->item(0))?->getAttribute('content');
            if (is_string($imageUrl)) {
                return $this->createPreviewImageUrl(UrlResolver::resolve($url, $imageUrl));
            }
        }

        // Fallback to Twitter Card image
        $nodes = $xpath->query("//meta[@name='twitter:image']");
        if ($nodes->length > 0) {
            $imageUrl = $this->getDomElement($nodes->item(0))?->getAttribute('content');
            if (is_string($imageUrl)) {
                return $this->createPreviewImageUrl(UrlResolver::resolve($url, $imageUrl));
            }
        }

        return $this->createPreviewImageUrl($url, true);
    }

    /**
     * Extract the favicon URL from {@code link[rel=icon]} or {@code link[rel="shortcut icon"]}.
     *
     * The raw href (which may be relative) is resolved against the page URL and wrapped in a
     * HAWKI favicon proxy route. Falls back to the domain-based proxy URL when no tag is found.
     */
    private function extractFaviconUrl(\DOMXPath $xpath, string $url): string
    {
        // Try to get the favicon from the <link rel="icon"> tag
        $nodes = $xpath->query("//link[@rel='icon']");
        if ($nodes->length > 0) {
            $faviconUrl = $this->getDomElement($nodes->item(0))?->getAttribute('href');
            if (is_string($faviconUrl)) {
                return $this->createFaviconUrl(UrlResolver::resolve($url, $faviconUrl));
            }
        }

        // Fallback to <link rel="shortcut icon">
        $nodes = $xpath->query("//link[@rel='shortcut icon']");
        if ($nodes->length > 0) {
            $faviconUrl = $this->getDomElement($nodes->item(0))?->getAttribute('href');
            if (is_string($faviconUrl)) {
                return $this->createFaviconUrl(UrlResolver::resolve($url, $faviconUrl));
            }
        }

        return $this->createFaviconUrl($url);
    }

    /**
     * Wrap an external URL in the HAWKI favicon proxy route, always using the {@code https://}
     * origin of the domain rather than the full path.
     *
     * This ensures the browser requests favicons through {@see FavIconProxy}.
     */
    private function createFaviconUrl(string $url): string
    {
        return $this->urlGenerator->route(
            'api.link-preview.favicon',
            ['url' => 'https://' . $this->getDomainFromUrl($url)]);
    }

    /**
     * Wrap an image URL in the HAWKI image proxy route.
     *
     * When {@param $isFallback} is true, a {@code fallback_} prefixed MD5 hash is used as the
     * proxy parameter so {@see ExternalImageProxy} knows to generate a gradient placeholder
     * instead of fetching from an external URL.
     */
    private function createPreviewImageUrl(string $url, bool $isFallback = false): string
    {
        $imageIdentifier = $isFallback ? 'fallback_' . md5($url) : $url;
        return $this->urlGenerator->route(
            'api.link-preview.image',
            ['url' => $imageIdentifier]);
    }

    /**
     * Build placeholder {@see WebsiteMetadata} for when the fetch or HTML parse fails.
     *
     * Derives a title from the domain name and still returns valid HAWKI proxy URLs for
     * image and favicon so the UI renders gracefully even on error.
     */
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

    /**
     * Derive a readable title from a URL when no page title can be extracted.
     *
     * Strips the {@code www.} prefix, removes the TLD (last dot-segment), then headline-cases
     * the remainder. Example: {@code www.example-site.com} → {@code "Example Site"}.
     */
    private function createFallbackTitle(string $url): string
    {
        // Remove www prefix, and all tdl suffixes (e.g. .com, .co.uk, .org, etc.) from the domain
        $domainWithoutWww = preg_replace('/^www\./', '', $this->getDomainFromUrl($url));
        $domainWithoutTld = preg_replace('/\.[^.]+$/', '', $domainWithoutWww);
        return Str::headline($domainWithoutTld);
    }

    /**
     * Extract the host from a URL, returning the full URL on parse failure.
     */
    private function getDomainFromUrl(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?? $url;
    }

    /**
     * Safely cast a DOM node to {@see \DOMElement}, returning null for any other type.
     *
     * XPath queries return {@see \DOMNode} instances, but attribute access via
     * {@see \DOMElement::getAttribute()} requires a {@see \DOMElement} specifically.
     */
    private function getDomElement(mixed $node): ?\DOMElement
    {
        return $node instanceof \DOMElement ? $node : null;
    }
}
