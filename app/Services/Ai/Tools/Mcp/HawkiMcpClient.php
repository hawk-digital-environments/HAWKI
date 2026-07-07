<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Mcp;


use App\Services\Ai\Tools\Values\McpToolDefinition;
use Illuminate\Container\RewindableGenerator;
use Mcp\Client\ClientSession;
use Mcp\Types\CallToolResult;
use Psr\Log\LoggerInterface;

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

    public function __destruct()
    {
        $this->session?->close();
    }

    public function getName(): string
    {
        $this->initializeIfNotAlready();
        return $this->session?->getInitializeResult()->serverInfo->name ?? 'unknown';
    }

    public function getVersion(): string
    {
        $this->initializeIfNotAlready();
        return $this->session?->getInitializeResult()->serverInfo->version ?? 'unknown';
    }

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
