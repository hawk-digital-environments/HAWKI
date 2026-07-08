<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use App\Services\ExternalContent\Events\ExternalImageResolvedFilterEvent;
use App\Services\ExternalContent\Events\ResolvingExternalImageFilterEvent;
use App\Services\ExternalContent\Values\ResolvedExternalImage;
use App\Services\System\Time\Clock;
use App\Utils\Imaging\RandomGradientImage;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;

readonly class ExternalImageProxy
{
    public function __construct(
        private ProxyClient     $client,
        private Repository      $cache,
        private LoggerInterface $logger,
        private Clock           $clock = new Clock()
    )
    {
    }

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
