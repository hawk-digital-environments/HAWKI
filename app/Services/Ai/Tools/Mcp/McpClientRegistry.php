<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp;

use App\Models\Ai\McpServer;

/** Reuse one client per current server configuration; discard replaced credentials in workers. */
final class McpClientRegistry
{
    /** @var array<int, array{configuration: string, client: HawkiMcpClient}> */
    private array $clients = [];

    public function __construct(private readonly McpClientFactory $factory)
    {
    }

    public function get(McpServer $server): HawkiMcpClient
    {
        $configuration = [];
        foreach (['url', 'type', 'api_key', 'additional_config', 'timeouts'] as $attribute) {
            $configuration[$attribute] = $server->getRawOriginal($attribute);
        }
        $fingerprint = hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR));
        $id = $server->getKey();
        if (($this->clients[$id]['configuration'] ?? null) !== $fingerprint) {
            $this->clients[$id] = [
                'configuration' => $fingerprint,
                'client' => $this->factory->createForServer($server),
            ];
        }
        return $this->clients[$id]['client'];
    }
}
