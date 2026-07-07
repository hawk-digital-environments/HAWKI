<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use App\Services\ExternalContent\Values\ResolvedExternalImage;
use App\Services\System\Time\Clock;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;

readonly class FavIconProxy
{
    public function __construct(
        private ProxyClient     $client,
        private Repository      $cache,
        private LoggerInterface $logger,
        private Clock           $clock = new Clock()
    )
    {
    }

    public function getFaviconOf(string $url): ResolvedExternalImage
    {
        return $this->cache->remember(
            key: "favicon_" . md5($url),
            ttl: $this->clock->now()->addDays(7),
            callback: function () use ($url) {
                // @todo filter event $url (has a $resolved property, can be set by handler, if set, return that instead of resolving again)

                try {
                    $icon = $this->fetchFaviconThroughGoogle($url)
                        ?? $this->makeFallbackFavicon();
                } catch (\Throwable $e) {
                    $this->logger->warning("Error fetching favicon for URL: $url. Error: " . $e->getMessage(), ['exception' => $e]);

                    $icon = $this->makeFallbackFavicon();
                }

                // @todo filter event $url $icon

                return $icon;
            }
        );
    }

    private function fetchFaviconThroughGoogle(string $url): ResolvedExternalImage|null
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $faviconUrl = "https://www.google.com/s2/favicons?domain={$domain}&sz=32";

        $response = $this->client->fetchOrThrow($faviconUrl, 2);

        if ($response->successful() && str_starts_with($response->header('Content-Type'), 'image/')) {

            // @todo detect if we got the google "no favicon" image, and if so, return a default favicon instead of the google one

            return new ResolvedExternalImage(
                content: $response->body(),
                mimeType: $response->header('Content-Type'),
            );
        }

        return null;
    }

    private function makeFallbackFavicon(): ResolvedExternalImage
    {
        return new ResolvedExternalImage(
            content: '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 128 128">
  <radialGradient id="a" cx="65" cy="5" r="105.9" gradientUnits="userSpaceOnUse">
    <stop offset=".2" stop-color="#01b3f7"/>
    <stop offset=".5" stop-color="#01b3f7"/>
  </radialGradient>
  <path fill="url(#a)" d="M113.3 28.6c-15-21-39.4-23-49.2-23.2a64 64 0 0 0-44.5 18.2A56 56 0 0 0 3.6 65a58 58 0 0 0 14.3 37.8 60 60 0 0 0 46.4 20.5h1c13.2-.3 36-4.1 49.5-26.3a61 61 0 0 0-1.5-68.4m3.9 31.3h-11.5a78 78 0 0 0-3.8-21.4h8.8a52 52 0 0 1 6.5 21.4M10.8 67.6h12q.6 10.3 4 21.6H17a49 49 0 0 1-6.2-21.6m57-55.9a43 43 0 0 1 21.4 18.8H67.8zm-7 .3v18.4h-22c8.2-13 20-17.6 22-18.4m0 26.4v21.3H30.6Q31 47 34.7 38.4zM22.5 59.6H11q1-11.6 7-21.3h8q-3.2 9-3.5 21.3m8.3 8 30 .1v21.5H35a76 76 0 0 1-4.2-21.5m30 29.6v19.2c-4-1.3-14.6-5.9-22.1-19.2zm6.9 19.3.1-19.3H90a36 36 0 0 1-22.3 19.3m.1-27.3V67.7l29.6.1a73 73 0 0 1-3.8 21.4zm0-29.5V38.4l25.4.1a66 66 0 0 1 4.2 21.3zm37.5-29.1h-6.7a57 57 0 0 0-8-12.1 47 47 0 0 1 14.7 12M37.5 19q-4.2 4.4-8 11.3h-5.8L26 28a53 53 0 0 1 11.6-9m-14 79.2-.9-1H30q3.3 6.8 8.4 12.6a54 54 0 0 1-14.9-11.6m67 12.1c3-3.6 6.7-8 8.8-13.1h6.5c-4 5-10.3 10.3-15.3 13.1M111 89.2h-8.7a85 85 0 0 0 3.4-21.4h11.5a54 54 0 0 1-6.2 21.4"/>
  <path fill="#188aff" d="m93.7 39.8-25.9-.1v-2.5l24.9.1zm-32.9-.2H34.2l1-2.5h25.6zm0 29.4-30-.1-.2-2.5 30.2.1zm36.6 0H67.8v-2.5l29.7.1zm19.7.1h-11.5l.1-2.5h11.6zm-5.8-29.4-9 .1-.8-2.4h8.6zM60.8 98.4H39.4L38 96.1l22.9-.2zm28.6-.1-21.6.1v-2.5l22.9.1zm-58.9.1-6.8.1-2-2.3h7.8zm-7.6-29.5-12-.1-.2-2.5 12.1.1zm2.7-29.4h-8.4l1.4-2.4h7.8zm79.1 59-6-.1 1.1-2.4 6.9.1zM38.8 30.3c.3-.4 3.5-7.6 9.7-12.8 6.3-5.3 12.1-7 12.4-7v2.7c-.1 0-5.3 1.3-11.2 6-7 5.7-10.8 11-10.9 11.1m50.4.2s-2.5-4.5-9.4-10.1a60 60 0 0 0-12-7.4v-2.6c.4.1 7 3 12.6 8a33 33 0 0 1 8.8 12zm16.1 0s-3.4-3.1-7-6c-3.5-2.8-6-4-6-4l-2.6-3.1c.2.1 5.9 3 9.4 6 3.6 3 6 6.8 6.1 7zm-81.6-.2v-.1c.2-.1 2.2-3.2 5.6-6.5 4.2-3.9 9-5.7 9-5.8l-2.2 2.6s-1.8.7-6.2 4.2c-3.4 2.8-6.1 5.6-6.2 5.6"/>
  <path fill="#188aff" d="M64.1 124.6c-41 0-54.6-34.5-54.8-34.8l.1.2a61 61 0 0 0 54.7 32h.1c21.2 0 34.5-8.8 41.9-16.2 8-8 12.3-15.5 12.3-15.6 0 .3-11.9 34.3-54.2 34.3"/>
</svg>',
            mimeType: 'image/svg+xml',
            isFallback: true
        );
    }

}
