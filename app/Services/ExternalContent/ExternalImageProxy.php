<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use App\Services\ExternalContent\Events\ExternalImageResolvedFilterEvent;
use App\Services\ExternalContent\Events\ResolvingExternalImageFilterEvent;
use App\Services\ExternalContent\Values\ResolvedExternalImage;
use App\Services\System\Time\CarbonClockInterface;
use App\Utils\Imaging\RandomGradientImage;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;

/**
 * Fetches external images and serves them through the HAWKI proxy, caching results for 24 hours.
 *
 * Proxying images prevents the browser from leaking the user's IP address to third-party
 * image hosts and enforces SSRF protection via {@see ProxyClient} on every outbound request.
 *
 * When the fetch fails or the response is not an image, a random gradient PNG placeholder
 * (160×90 px, 16:9) is generated and cached in its place so the caller always receives a
 * usable image.
 *
 * Both steps are extensible through filter events:
 *  - {@see ResolvingExternalImageFilterEvent} fires before the fetch and allows listeners
 *    to short-circuit with a pre-resolved image.
 *  - {@see ExternalImageResolvedFilterEvent} fires after the fetch (or fallback generation)
 *    and allows listeners to post-process the image before it is cached.
 *
 * Usage:
 * ```php
 * $image = $externalImageProxy->get('https://example.com/photo.jpg');
 * return response($image->content, 200)->header('Content-Type', $image->mimeType);
 * ```
 *
 * @see ResolvingExternalImageFilterEvent  dispatched before fetching; listeners can short-circuit
 * @see ExternalImageResolvedFilterEvent   dispatched after fetching; listeners can post-process
 */
readonly class ExternalImageProxy
{
    public function __construct(
        private ProxyClient          $client,
        private Repository           $cache,
        private LoggerInterface      $logger,
        private CarbonClockInterface $clock
    )
    {
    }

    /**
     * Fetch and cache an external image, returning a {@see ResolvedExternalImage}.
     *
     * Cached for 24 hours, keyed by MD5 of the URL. If the URL does not return a 2xx response
     * with an {@code image/} Content-Type, or if any error occurs, a random gradient fallback
     * image is returned and cached so subsequent calls within the TTL are served without retrying.
     */
    public function get(string $url): ResolvedExternalImage
    {
        return $this->cache->remember(
            key: 'external_image_proxy:' . md5($url),
            ttl: $this->clock->now()->addHours(24),
            callback: function () use ($url): ResolvedExternalImage {
                try {
                    $preEvent = ResolvingExternalImageFilterEvent::dispatch($url);
                    if ($preEvent->getResolved() !== null) {
                        return $preEvent->getResolved();
                    }

                    $response = $this->client->fetchOrThrow($url, 2);

                    if (!str_starts_with($response->header('Content-Type'), 'image/')) {
                        /** @noinspection ThrowRawExceptionInspection */
                        throw new \Exception("URL did not return an image. Content-Type: " . $response->header('Content-Type'));
                    }

                    $image = new ResolvedExternalImage(
                        content: $response->body(),
                        mimeType: $response->header('Content-Type'),
                        isFallback: false
                    );
                } catch (\Throwable $e) {
                    $this->logger->warning("Error fetching image from URL: $url. Error: " . $e->getMessage(), ['exception' => $e]);
                    $image = $this->makeFallbackImage();
                }

                return ExternalImageResolvedFilterEvent::dispatch($url, $image)->getImage();
            }
        );
    }

    /**
     * Generate a random gradient PNG placeholder for when the real image cannot be fetched.
     *
     * Dimensions are 160×90 (16:9) using HAWKI brand colours. The returned image always has
     * {@see ResolvedExternalImage::$isFallback} set to true.
     *
     * Public because {@see \App\Http\Controllers\LinkPreviewController} calls it directly when
     * a {@code fallback_} prefixed URL parameter is requested.
     */
    public function makeFallbackImage(): ResolvedExternalImage
    {
        $image = new RandomGradientImage(
            width: 160,
            height: 90,
            colors: [
                '#5693BD',
                '#010031',
                '#7FA8FF',
                '#C07200',
                '#7EB3FF',
                '#C1B1F1',
                '#A0B3EEFF',
                '#FBCB6A',
                '#F57A3D',
                '#F9A386',
                '#F0759E'
            ]
        );

        return new ResolvedExternalImage(
            content: (string)$image,
            mimeType: 'image/png',
            isFallback: true
        );
    }
}
