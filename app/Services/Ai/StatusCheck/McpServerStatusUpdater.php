<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use App\Services\Ai\StatusCheck\Events\McpServerStatusCheckedEvent;
use App\Services\Ai\StatusCheck\Events\McpServerStatusCheckFailedEvent;
use App\Services\Ai\StatusCheck\Events\McpStatusCheckCompletedEvent;
use App\Services\Ai\StatusCheck\Events\McpStatusCheckStartingEvent;
use App\Services\Ai\Tools\Mcp\McpClientFactory;
use App\Services\Ai\Tools\Repositories\McpServerRepository;
use App\Services\Ai\Values\OnlineStatus;
use App\Utils\JobMetrics;
use Psr\Log\LoggerInterface;

/**
 * Polls every registered MCP server and updates its online status in the database.
 *
 * The updater fetches all servers from {@see McpServerRepository}, connects to each
 * via {@see McpClientFactory}, and pings it. The result is persisted as
 * {@see OnlineStatus::ONLINE} or {@see OnlineStatus::OFFLINE}. Exceptions during a
 * single server check are caught and recorded as errors without aborting the remaining
 * servers.
 *
 * Progress and results are communicated through {@see JobMetrics} (returned from
 * {@see run()}) and a series of domain events dispatched throughout the process:
 * - {@see McpStatusCheckStartingEvent} — before the server loop begins
 * - {@see McpServerStatusCheckedEvent} — after each successful ping (online or offline)
 * - {@see McpServerStatusCheckFailedEvent} — when an exception is thrown for a server
 * - {@see McpStatusCheckCompletedEvent} — after all servers have been processed
 *
 * Example — invoke from a scheduled artisan command:
 * ```php
 * $metrics = app(McpServerStatusUpdater::class)->run();
 *
 * $this->info(sprintf(
 *     'Status check complete: %d online, %d offline',
 *     $metrics->get('online'),
 *     $metrics->get('offline'),
 * ));
 *
 * if ($metrics->hasErrors()) {
 *     $this->warn('Errors occurred during status check:');
 *     foreach ($metrics->getErrors() as $error) {
 *         $this->warn('  ' . $error);
 *     }
 * }
 * ```
 */
readonly class McpServerStatusUpdater
{
    public const string METRIC_SERVER_COUNT = 'Servers checked';
    public const string METRIC_SERVER_ONLINE = 'Servers ONLINE';
    public const string METRIC_SERVER_OFFLINE = 'Servers OFFLINE';

    public function __construct(
        private McpServerRepository $mcpServerRepository,
        private McpClientFactory    $mcpClientFactory,
        private LoggerInterface     $logger
    )
    {
    }

    /**
     * Runs the status check for all registered MCP servers and returns aggregated metrics.
     *
     * Counters recorded on the returned {@see JobMetrics}:
     * - `server_count`   — total servers for which a ping was attempted (excludes exceptions)
     * - `server_online`  — servers that responded to the ping successfully
     * - `server_offline` — servers whose ping failed or that threw an exception
     *
     * If the server list cannot be retrieved from the repository, the method returns
     * an empty {@see JobMetrics} instance immediately without dispatching any events.
     * Individual server failures are caught, logged, and recorded as errors on the
     * metrics object but do not abort the remaining servers.
     */
    public function run(): JobMetrics
    {
        $metrics = new JobMetrics('MCP Server Status Update', $this->logger);

        $metrics->announceStart();

        try {
            $servers = $this->mcpServerRepository->findAll();
        } catch (\Throwable $e) {
            $metrics->error('Failed to retrieve MCP servers from repository: ' . $e->getMessage(), ['exception' => $e]);
            return $metrics;
        }

        McpStatusCheckStartingEvent::dispatch($metrics);

        foreach ($servers as $server) {
            try {
                $metrics->debug(sprintf(
                    'Pinging MCP server %s to check status',
                    $server->url
                ));

                $client = $this->mcpClientFactory->createForServer($server);

                if ($client->ping()) {
                    $metrics->info(sprintf(
                        'MCP Server %s is ONLINE',
                        $server->url
                    ));
                    $this->mcpServerRepository->setOnlineStatus($server, OnlineStatus::ONLINE);
                    $metrics->increment(self::METRIC_SERVER_ONLINE);
                } else {
                    $metrics->warning(sprintf(
                        'MCP Server %s is OFFLINE',
                        $server->url
                    ));
                    $this->mcpServerRepository->setOnlineStatus($server, OnlineStatus::OFFLINE);
                    $metrics->increment(self::METRIC_SERVER_OFFLINE);
                }
                $metrics->increment(self::METRIC_SERVER_COUNT);

                McpServerStatusCheckedEvent::dispatch($server, $client, $server->status, $metrics);
            } catch (\Throwable $e) {
                $metrics
                    ->increment(self::METRIC_SERVER_OFFLINE)
                    ->error(sprintf(
                        'Error checking status of MCP server %s: %s, marking as OFFLINE',
                        $server->url,
                        $e->getMessage()
                    ), ['exception' => $e]);

                $this->mcpServerRepository->setOnlineStatus($server, OnlineStatus::OFFLINE);

                McpServerStatusCheckFailedEvent::dispatch($server, $e, $metrics);
            }
        }

        McpStatusCheckCompletedEvent::dispatch($metrics);

        $metrics->announceCompletion();

        return $metrics;
    }
}
