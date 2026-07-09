<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Mcp;


use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Values\McpServerTimeouts;
use App\Services\Ai\Tools\Values\McpServerType;
use App\Utils\Arrays\RecursiveMerger;
use Mcp\Client\Client;
use Psr\Log\LoggerInterface;

/**
 * Creates {@see HawkiMcpClient} instances from either a {@see McpServer} model or raw config values.
 *
 * The factory maps HAWKI server configuration to the `mcp/mcp-php` {@see Client} connection
 * parameters, branching on transport type:
 *  - {@see McpServerType::STDIO}: uses `args` and `env` from the merged config.
 *  - {@see McpServerType::SSE} / {@see McpServerType::HTTP}: uses `headers` and `http_options`.
 *
 * API keys are injected as `Authorization: Bearer` headers for HTTP/SSE transports.
 * Timeout values from {@see McpServerTimeouts} are forwarded to the underlying HTTP transport.
 * Additional per-server config from the database is deep-merged on top of the defaults via
 * {@see RecursiveMerger}, allowing operators to supply arbitrary transport options.
 *
 * The returned client holds the session factory as a closure; the actual TCP/stdio connection
 * is not established until the client is first used.
 */
class McpClientFactory
{
    public function __construct(
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * Convenience method that reads all connection parameters from a {@see McpServer} model
     * and delegates to {@see createForConfig()}.
     */
    public function createForServer(McpServer $server): HawkiMcpClient
    {
        return $this->createForConfig(
            $server->url,
            $server->type,
            $server->additional_config,
            $server->api_key,
            $server->timeouts
        );
    }

    /**
     * Builds a {@see HawkiMcpClient} from raw connection parameters.
     *
     * Prefer {@see createForServer()} when you have a model; use this directly only when
     * constructing a client from config-file values (e.g. in {@see ConfigSyncMigrationTrait})
     * before the server record has been persisted.
     *
     * @param string               $url     Command path (STDIO) or server URL (SSE/HTTP).
     * @param McpServerType        $type    Transport type; controls how `$config` is interpreted.
     * @param array|null           $config  Additional transport options deep-merged onto defaults.
     * @param string|null          $apiKey  When set, added as `Authorization: Bearer` for HTTP/SSE transports.
     * @param McpServerTimeouts|null $timeouts  Connection and read timeout overrides; falls back to 10 s read timeout when null.
     */
    public function createForConfig(
        string                 $url,
        McpServerType          $type,
        array|null             $config = null,
        string|null            $apiKey = null,
        McpServerTimeouts|null $timeouts = null,
    ): HawkiMcpClient
    {
        $client = new Client($this->logger);

        $headers = [];
        /** Mapped at {@see Client::connect} to {@see \Mcp\Client\Transport\HttpConfiguration} */
        $httpOptions = [];
        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }
        if ($timeouts !== null) {
            if ($timeouts->connectionTimeout !== null) {
                $httpOptions['connectionTimeout'] = $timeouts->connectionTimeout;
            }
            if ($timeouts->readTimeout !== null) {
                $httpOptions['readTimeout'] = $timeouts->readTimeout;
            }
            if ($timeouts->sseIdleTimeout !== null) {
                $httpOptions['sseIdleTimeout'] = $timeouts->sseIdleTimeout;
            }
        }

        $fullConfig = RecursiveMerger::merge(
            [
                'args' => [],
                'headers' => $headers,
                'http_options' => $httpOptions,
                'env' => []
            ],
            $config ?? []
        );

        $clientArgs = $type === McpServerType::STDIO
            ? $fullConfig['args']
            : $fullConfig['headers'];
        $clientEnv = $type === McpServerType::STDIO
            ? $fullConfig['env']
            : $fullConfig['http_options'];

        return new HawkiMcpClient(
            url: $url,
            sessionFactory: fn() => $client->connect(
                commandOrUrl: $url,
                args: $clientArgs,
                env: $clientEnv,
                readTimeout: (float)($timeouts->readTimeout ?? 10)
            ),
            logger: $this->logger
        );
    }
}
