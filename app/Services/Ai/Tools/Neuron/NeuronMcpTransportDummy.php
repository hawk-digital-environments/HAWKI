<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Neuron;

use App\Models\Ai\AiTool;
use NeuronAI\MCP\McpClient;
use NeuronAI\MCP\McpTransportInterface;

/**
 * @internal Internal implementation detail to hook ourselves into {@see McpClient} without actually connecting to a real MCP server.
 * This allow us to reuse the tool hydration logic in McpClient, without having to connect to the server every time we want to hydrate a tool.
 */
final class NeuronMcpTransportDummy implements McpTransportInterface
{
    private array $nextResponse = [];
    private AiTool|null $tool = null;

    /**
     * Handles an outgoing MCP JSON-RPC request by building an in-memory response.
     *
     * Recognised methods:
     * - `initialize`  → replies with an empty init acknowledgement (no `result` key required).
     * - `tools/list`  → returns the tool stored via {@see setTool()} under `result.tools`;
     *                   an empty array is returned when no tool has been set.
     *
     * The response `id` is always echoed back from the request, as required by JSON-RPC.
     * The built response is stored internally and returned by the next {@see receive()} call.
     */
    public function send(array $data): void
    {
        $this->nextResponse = [];
        $this->sendInitialize($data) || $this->sendToolsList($data);
        $this->nextResponse['id'] = $data['id'] ?? null;
    }

    /**
     * Handles the `initialize` method by returning an empty response, which is sufficient for MCP initialization acknowledgment.
     */
    private function sendInitialize(array $data): bool
    {
        return ($data['method'] ?? null) === 'initialize';
    }

    /**
     * Handles the `tools/list` method by returning the currently set tool's `mcp_config` under `result.tools`.
     * If no tool is set, returns an empty array.
     */
    private function sendToolsList(array $data): bool
    {
        if (($data['method'] ?? null) === 'tools/list') {
            $tools = [];
            if ($this->tool) {
                $tools[] = $this->tool->mcp_config ?? [];
            }
            $this->nextResponse['result']['tools'] = $tools;
            return true;
        }

        return false;
    }

    /**
     * Sets the tool whose `mcp_config` will be returned in the next `tools/list` response.
     * Must be called before {@see McpConnector::tools()} triggers the `tools/list` exchange.
     */
    public function setTool(AiTool $tool): void
    {
        $this->tool = $tool;
    }

    /**
     * Returns the response payload assembled by the most recent {@see send()} call.
     */
    public function receive(): array
    {
        return $this->nextResponse;
    }

    public function connect(): void
    {
    }

    public function disconnect(): void
    {
    }
}
