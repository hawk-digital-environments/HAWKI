<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Neuron\Events;

use App\Events\Traits\DispatchableFilter;
use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;

/**
 * Filter event dispatched after an MCP tool has been called and a result is available.
 *
 * Listeners may read the original input and tool context, and optionally replace the result
 * that will be returned to the AI agent via {@see setResult()}.
 *
 * This makes it possible for plugins to:
 * - Post-process or sanitise the raw MCP response before the AI agent sees it.
 * - Enrich the result with additional data.
 * - Replace error responses with a controlled fallback string.
 *
 * The result is a JSON-encoded string produced from the MCP server's response.
 * Read-only properties ($input, $tool, $mcpClient) expose context but cannot be replaced.
 * Only $result is writable.
 */
class McpToolCalledFilterEvent
{
    use DispatchableFilter;

    /**
     * The JSON-encoded result from the MCP server (or from a before-filter short-circuit).
     */
    private string $result;

    public function __construct(
        string                          $result,
        /** The input arguments that were passed to the tool by the AI agent. */
        private readonly array          $arguments,
        /** The tool record that was invoked. */
        private readonly AiTool         $tool,
        /** The MCP client that performed the call. */
        private readonly HawkiMcpClient $mcpClient,
    )
    {
        $this->result = $result;
    }

    /**
     * Returns the current result that will be returned to the AI agent.
     * Override via {@see setResult()} to replace the value.
     */
    public function getResult(): string
    {
        return $this->result;
    }

    /**
     * Replace the result that will be returned to the AI agent.
     * The value should be a JSON-encoded string matching the MCP tool response format.
     */
    public function setResult(string $result): void
    {
        $this->result = $result;
    }

    /**
     * The input arguments the AI agent passed to the tool.
     *
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * The tool that was called.
     */
    public function getTool(): AiTool
    {
        return $this->tool;
    }

    /**
     * The MCP client that performed the actual server call.
     */
    public function getMcpClient(): HawkiMcpClient
    {
        return $this->mcpClient;
    }
}
