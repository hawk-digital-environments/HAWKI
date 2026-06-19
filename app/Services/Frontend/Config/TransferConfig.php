<?php
declare(strict_types=1);


namespace App\Services\Frontend\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class TransferConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * Base URL for transfer-related API endpoints, e.g. for file uploads.
     * This is used by the frontend to know where to send transfer-related requests.
     * @var string
     */
    public readonly string $baseUrl;

    /**
     * Websocket configuration for real-time transfer updates.
     * This is used by the frontend to establish websocket connections for transfer-related events.
     * @var WebsocketTransferConfig
     */
    public readonly WebsocketTransferConfig $websocket;

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
     * @inheritDoc
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
