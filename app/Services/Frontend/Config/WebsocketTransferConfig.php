<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Utils\Casts\CastedValue;
use App\Utils\Casts\Values\CastType;
use Illuminate\Config\Repository;

class WebsocketTransferConfig extends AbstractConfig
{
    public readonly string $key;
    public readonly string $host;
    #[CastedValue(CastType::INT)]
    public readonly string|int $port;
    public readonly bool $forceTls;
    public readonly string|null $path;

    /**
     * @inheritDoc
     */
    public static function make(Repository $repo): static
    {
        $appUrl = $repo->get('app.url');
        $reverbScheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'http';
        $reverbHost = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
        $reverbPath = parse_url($appUrl, PHP_URL_PATH);
        $reverbPort = parse_url($appUrl, PHP_URL_PORT) ?? ($reverbScheme === 'https' ? 443 : 80);

        // Check for overrides using (VITE_REVERB_...)
        $viteReverbHost = $repo->get('reverb.frontend.host');
        $viteReverbPort = $repo->get('reverb.frontend.port');
        $viteReverbScheme = $repo->get('reverb.frontend.scheme');
        if ($viteReverbHost) {
            $reverbHost = $viteReverbHost;
            $reverbPath = null; // If host is overridden, we assume the path is just "/"
        }
        if ($viteReverbPort) {
            $reverbPort = $viteReverbPort;
        }
        if ($viteReverbScheme) {
            $reverbScheme = $viteReverbScheme;
        }

        return self::fromArray([
            'key' => $repo->get('reverb.frontend.key'),
            'host' => $reverbHost,
            'port' => (int)$reverbPort,
            'forceTls' => $reverbScheme === 'https',
            'path' => $reverbPath ? rtrim($reverbPath, '/') . '/ws/transfer' : '/ws/transfer',
        ]);
    }
}
