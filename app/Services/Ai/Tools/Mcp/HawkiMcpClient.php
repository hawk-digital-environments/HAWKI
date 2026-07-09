<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Mcp;


use App\Services\Ai\Tools\Values\McpToolDefinition;
use Illuminate\Container\RewindableGenerator;
use Mcp\Client\ClientSession;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * Lazily-initialized MCP client for a single MCP server.
 *
 * The underlying {@see ClientSession} is created on first use via the injected factory closure
 * and then reused for the lifetime of this instance. The session is also lazily initialized
 * (via `initialize()`) if the server has not yet exchanged capabilities.
 *
 * Tool definitions fetched via {@see listToolDefinitions()} are cached in a
 * {@see RewindableGenerator} so repeated calls within the same request do not re-query the
 * MCP server.
 *
 * Instances are constructed by {@see McpClientFactory} and managed as lazy singletons per
 * {@see McpServer} by the `AiServiceProvider::MCP_CLIENT_LIST` binding.
 */
class HawkiMcpClient
{
    private ClientSession|null $session = null;
    private RewindableGenerator|null $cachedToolDefinitions = null;

    public function __construct(
        private readonly string          $url,
        /**
         * @var \Closure(): ClientSession
         */
        private readonly \Closure        $sessionFactory,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * Closes the MCP session when this instance is garbage-collected,
     * ensuring the underlying transport connection is released.
     */
    public function __destruct()
    {
        $this->session?->close();
    }

    /**
     * Returns the server's self-reported name from the MCP initialization handshake.
     * Triggers session initialization on first call.
     */
    public function getName(): string
    {
        $this->initializeIfNotAlready();
        return $this->session?->getInitializeResult()->serverInfo->name ?? 'unknown';
    }

    /**
     * Returns the server's self-reported version from the MCP initialization handshake.
     * Triggers session initialization on first call.
     */
    public function getVersion(): string
    {
        $this->initializeIfNotAlready();
        return $this->session?->getInitializeResult()->serverInfo->version ?? 'unknown';
    }

    /**
     * Sends a ping to the MCP server and returns whether it succeeded.
     * Used by {@see McpServerStatusUpdater} for health checks.
     * Failures are logged at warning level and return false rather than throwing.
     */
    public function ping(): bool
    {
        try {
            $this->initializeIfNotAlready();
            $this->session->sendPing();
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'Ping to MCP server %s failed: %s',
                $this->url,
                $e->getMessage()
            ), ['exception' => $e]);

            return false;
        }
    }

    /**
     * Invokes a named tool on the MCP server with the given arguments.
     *
     * Errors are logged at error level before re-throwing so callers (e.g. {@see LaravelMcpTool})
     * receive the original exception and can convert it into an AI-readable error response.
     *
     * @throws \Throwable on any transport or protocol error.
     */
    public function callTool(string $name, ?array $arguments = null): CallToolResult
    {
        try {
            $this->initializeIfNotAlready();
            return $this->session->callTool(
                name: $name,
                arguments: $arguments
            );
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Call to MCP tool %s on server %s failed: %s',
                $name,
                $this->url,
                $e->getMessage()
            ), ['exception' => $e, 'arguments' => $arguments]);

            throw $e;
        }
    }

    /**
     * Returns all tools advertised by the MCP server as {@see McpToolDefinition} value objects.
     *
     * The result is lazily generated and cached as a {@see RewindableGenerator} after the first
     * call, so subsequent calls within the same request reuse the cached list without a second
     * network round-trip. The raw MCP tool data is JSON-encoded and decoded to produce a plain
     * array suitable for storing in the database as `mcp_config`. An optional `hawkiCapability`
     * field on the raw tool object is forwarded as the definition's capability string, allowing
     * MCP servers to declare which HAWKI capability they fulfil.
     *
     * @return \Traversable<int, McpToolDefinition>
     */
    public function listToolDefinitions(): \Traversable
    {
        if (!$this->cachedToolDefinitions) {
            $this->initializeIfNotAlready();
            $rawTools = $this->session->listTools();
            $this->cachedToolDefinitions = new RewindableGenerator(
                function () use ($rawTools) {
                    foreach ($rawTools->tools as $tool) {
                        yield new McpToolDefinition(
                            name: $tool->name,
                            description: $tool->description,
                            config: json_decode(json_encode($tool), true),
                            // Allow the MCP server to send us additional info
                            capability: $tool->hawkiCapability ?? null
                        );
                    }
                },
                count($rawTools->tools)
            );
        }
        return $this->cachedToolDefinitions;
    }

    private function initializeIfNotAlready(): void
    {
        if (!$this->session) {
            $this->session = ($this->sessionFactory)();
        }
        if (!$this->session->isInitialized()) {
            $this->session->initialize();
        }
    }
}
