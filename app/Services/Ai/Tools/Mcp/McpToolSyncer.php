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
use App\Services\Ai\Tools\Mcp\Events\McpToolSyncedEvent;
use App\Services\Ai\Tools\Repositories\AiToolRepository;
use App\Services\Ai\Tools\Repositories\McpServerRepository;
use App\Services\Ai\Values\OnlineStatus;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Singleton;
use Psr\Log\LoggerInterface;

/**
 * Synchronises MCP tool definitions from live MCP servers into the `ai_tools` database table.
 *
 * Runs at deployment time via `ai:tools:sync --mcp-only` (or the combined command). Only servers
 * whose {@see OnlineStatus} is {@see OnlineStatus::ONLINE} are contacted; offline servers are
 * skipped with a warning.
 *
 * For each eligible server the syncer:
 *  1. Opens a {@see HawkiMcpClient} connection and lists available tools.
 *  2. Upserts each tool via {@see AiToolRepository::upsertMcp()}, collecting the persisted IDs.
 *  3. After all tools are processed, calls {@see AiToolRepository::removeAllMcpToolsOf()} to
 *     delete any `ai_tools` rows for that server that were *not* returned in the current listing
 *     (i.e. tools that have been removed from the MCP server).
 *
 * Failures for individual tools are caught and recorded without aborting the server's remaining
 * tools. Failures for an entire server are also caught and recorded without aborting remaining servers.
 *
 * Events emitted during a sync:
 *  - {@see McpToolSyncStartingEvent}          — before iteration begins.
 *  - {@see McpServerToolSyncStartingEvent}    — before each server's tools are fetched.
 *  - {@see McpToolSyncedEvent}                — after each successful tool upsert.
 *  - {@see McpToolSyncFailedEvent}            — when a single tool upsert throws.
 *  - {@see McpServerToolCleanupStartingEvent} — before stale tool removal for a server.
 *  - {@see McpServerToolSyncCompletedEvent}   — after a server's sync (including cleanup) succeeds.
 *  - {@see McpServerToolSyncFailedEvent}      — when an entire server's sync throws.
 *  - {@see McpToolSyncCompletedEvent}         — after all servers have been processed.
 */
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

    /**
     * Iterates all known MCP servers, fetches their tool listings, and upserts the results.
     * Stale tools (present in the DB but absent from the server's current listing) are deleted
     * after each server's tools are processed. Returns collected metrics for the whole run.
     */
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

                        McpToolSyncedEvent::dispatch($server, $definition, $synced, $metrics);

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
