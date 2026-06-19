<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Neuron\Events;

use App\Events\Traits\DispatchableFilter;
use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;

/**
 * Filter event dispatched immediately before an MCP tool is called.
 *
 * Listeners may inspect the incoming input, the tool definition, and the MCP client, and
 * optionally short-circuit the actual MCP call by providing a result via {@see setResult()}.
 * When a result is set, the real MCP request is skipped entirely and the supplied value is
 * returned to the AI agent instead.
 *
 * This makes it possible for plugins to:
 * - Cache or mock tool responses without hitting the MCP server.
 * - Validate or reject tool inputs by throwing an exception from a listener.
 * - Inject a synthetic result for testing or controlled environments.
 *
 * Read-only properties ($input, $tool, $mcpClient) expose context but cannot be replaced.
 * Only $result is writable.
 */
class BeforeCallingMcpToolFilterEvent
{
    use DispatchableFilter;

    /**
     * The result to return to the AI agent instead of calling the MCP server.
     * Remains null unless a listener explicitly provides a value.
     */
    private string|null $result;

    /**
     * Returns the current result value.
     * A non-null value means a listener has short-circuited the real MCP call.
     */
    public function getResult(): string|null
    {
        return $this->result;
    }

    /**
     * Provide a result and skip the actual MCP server call.
     * The value must be a JSON-encoded string matching the MCP tool response format.
     */
    public function setResult(string $result): void
    {
        $this->result = $result;
    }

    public function __construct(
        string|null                     $result,
        /** The input arguments passed to the tool by the AI agent. */
        private readonly array          $arguments,
        /** The tool record that is about to be invoked. */
        private readonly AiTool         $tool,
        /** The MCP client that would be used to make the real call. */
        private readonly HawkiMcpClient $mcpClient,
    )
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
     * The tool that is about to be called.
     */
    public function getTool(): AiTool
    {
        return $this->tool;
    }

    /**
     * The MCP client that would perform the actual server call.
     */
    public function getMcpClient(): HawkiMcpClient
    {
        return $this->mcpClient;
    }
}
