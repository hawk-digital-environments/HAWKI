<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Frontend config for file-transfer functionality, exposed under the `transfer` key.
 *
 * Provides the base API URL for upload/download endpoints and, for authenticated users,
 * the WebSocket connection details required to receive real-time transfer progress events.
 * WebSocket credentials are withheld from unauthenticated responses to prevent information leakage.
 */
class TransferConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * Base URL of the HAWKI application, used as the root for all transfer-related API requests.
     */
    public readonly string $baseUrl;

    /**
     * WebSocket connection details for real-time transfer events (progress, completion, errors).
     * Only included in the public response for authenticated users.
     */
    public readonly WebsocketTransferConfig $websocket;

    /**
     * Builds the transfer config from `app.url` and the reverb WebSocket settings.
     */
    public static function make(Repository $repo): static
    {
        return self::fromArray([
            'baseUrl' => $repo->get('app.url'),
            'websocket' => WebsocketTransferConfig::make($repo),
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'transfer';
    }

    /**
     * Returns the base URL for all callers, plus WebSocket credentials only for authenticated users.
     * Unauthenticated requests receive only `baseUrl` so the frontend can still reach the login endpoint.
     */
    public function toPublicArray(Request $request): array|null
    {
        $fullConfig = [
            'baseUrl' => $this->baseUrl
        ];

        if ($request->user()) {
            $fullConfig['websocket'] = [
                'key' => $this->websocket->key,
                'host' => $this->websocket->host,
                'port' => $this->websocket->port,
                'forceTls' => $this->websocket->forceTls,
                'path' => $this->websocket->path,
            ];
        }

        return $fullConfig;
    }
}
