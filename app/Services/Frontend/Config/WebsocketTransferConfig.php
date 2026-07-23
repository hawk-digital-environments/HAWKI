<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Utils\Casts\CastedValue;
use App\Utils\Casts\Values\CastType;
use Illuminate\Config\Repository;

/**
 * WebSocket connection parameters for the Laravel Reverb server, used by the frontend
 * to establish real-time connections for transfer events.
 *
 * Derived from `app.url` by default. Individual components can be overridden via the
 * `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, and `VITE_REVERB_SCHEME` environment variables,
 * which are useful when the Reverb server is exposed on a different host or port than the
 * main application (e.g. in local Docker setups).
 */
class WebsocketTransferConfig extends AbstractConfig
{
    /** Reverb application key, used by the frontend to identify the WebSocket application. */
    public readonly string $key;

    /** Hostname of the Reverb WebSocket server. */
    public readonly string $host;

    #[CastedValue(CastType::INT)]
    /** Port the Reverb server listens on. */
    public readonly string|int $port;

    /** When true, the frontend connects over `wss://` (TLS). Derived from the scheme of `app.url` or `VITE_REVERB_SCHEME`. */
    public readonly bool $forceTls;

    /** URL path prefix for the WebSocket endpoint, e.g. `/ws/transfer` or `/app/ws/transfer`. */
    public readonly string|null $path;

    /**
     * Derives WebSocket parameters from `app.url` and applies any reverb-specific overrides.
     *
     * The path is built by appending `/ws/transfer` to the path segment of `app.url`.
     * When `reverb.frontend.host` is set, the path resets to `/ws/transfer` because the
     * override host is assumed to be the WebSocket root without any sub-path prefix.
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
