<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Mcp;

use App\Services\Ai\Tools\Mcp\Events\McpServerToolCleanupStartingEvent;
use App\Services\Ai\Tools\Mcp\Events\McpServerToolSyncCompletedEvent;
use App\Services\Ai\Tools\Mcp\Events\McpServerToolSyncFailedEvent;
use App\Services\Ai\Tools\Mcp\Events\McpServerToolSyncStartingEvent;
use App\Services\Ai\Tools\Mcp\Events\McpToolSyncCompletedEvent;
use App\Services\Ai\Tools\Mcp\Events\McpToolSyncFailedEvent;
use App\Services\Ai\Tools\Mcp\Events\McpToolSyncStartingEvent;
use App\Services\Ai\Tools\Repositories\AiToolRepository;
use App\Services\Ai\Tools\Repositories\McpServerRepository;
use App\Services\Ai\Values\OnlineStatus;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Singleton;
use Psr\Log\LoggerInterface;

#[Singleton]
readonly class McpToolSyncer
{
    public function __construct(
        private McpClientFactory    $clientFactory,
        private McpServerRepository $serverRepository,
        private AiToolRepository    $toolRepository,
        private LoggerInterface     $logger
    )
    {
    }

    public function sync(): JobMetrics
    {
        $metrics = new JobMetrics('MCP Tool Sync', $this->logger);

        $metrics->announceStart();

        McpToolSyncStartingEvent::dispatch($metrics);

        foreach ($this->serverRepository->findAll() as $server) {
            if ($server->status !== OnlineStatus::ONLINE) {
                $metrics->warning(sprintf(
                    'Skipping MCP server %s for tool sync because it is not marked as online (status: %s)',
                    $server->url,
                    $server->status->value
                ));
                continue;
            }

            try {
                $syncedToolIds = [];
                $client = $this->clientFactory->createForServer($server);

                McpServerToolSyncStartingEvent::dispatch($server, $client, $metrics);

                foreach ($client->listToolDefinitions() as $definition) {
                    try {
                        $this->logger->debug(sprintf(
                            'Discovered tool from MCP server %s: %s %s',
                            $server->url,
                            $definition->name,
                            $definition->capability ? "(capability: {$definition->capability})" : ''
                        ));

                        $synced = $this->toolRepository->upsertMcp($definition, $server);

                        // @todo event $server $definition $synced $metrics

                        $syncedToolIds[] = $synced->id;
                        $metrics->increment('MCP Tools synced');
                    } catch (\Throwable $e) {
                        $metrics->error(sprintf(
                            'Failed to sync tool %s from MCP server %s: %s',
                            $definition->name,
                            $server->url,
                            $e->getMessage()
                        ), ['exception' => $e]);
                        McpToolSyncFailedEvent::dispatch($server, $client, $definition, $e, $metrics);
                    }
                }

                McpServerToolCleanupStartingEvent::dispatch($server, $client, $syncedToolIds, $metrics);

                $this->toolRepository->removeAllMcpToolsOf($server, $syncedToolIds);

                McpServerToolSyncCompletedEvent::dispatch($server, $client, $metrics);
            } catch (\Throwable $e) {
                $metrics->error('Failed to sync MCP tools for server ' . $server->url . ': ' . $e->getMessage(), ['exception' => $e]);
                McpServerToolSyncFailedEvent::dispatch($server, $e, $metrics);
            }
        }

        McpToolSyncCompletedEvent::dispatch($metrics);

        $metrics->announceCompletion();

        return $metrics;
    }
}
