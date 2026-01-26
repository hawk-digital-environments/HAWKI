<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\Value\ToolResult;

/**
 * Abstract base class for MCP (Model Context Protocol) tools
 *
 * MCP tools communicate with external MCP servers to execute their logic.
 * This base class handles the common execution flow and error handling.
 */
abstract class AbstractMCPTool extends AbstractTool implements MCPToolInterface
{
    /**
     * Get MCP server configuration
     * Must return an array with at least 'url' key
     */
    abstract public function getMCPServerConfig(): array;

    /**
     * Execute the MCP-specific logic
     * Subclasses implement this to communicate with their MCP server
     */
    abstract protected function executeMCP(array $arguments): mixed;

    /**
     * Check if the MCP server is available
     */
    public function isServerAvailable(): bool
    {
        $config = $this->getMCPServerConfig();
        return !empty($config['url'] ?? null);
    }

    /**
     * Execute the tool by forwarding to MCP server
     *
     * This method handles the common flow:
     * 1. Check server availability
     * 2. Execute MCP logic (delegated to subclass)
     * 3. Handle errors and return standardized result
     */
    final public function execute(array $arguments, string $toolCallId): ToolResult
    {
        if (!$this->isServerAvailable()) {
            return $this->error('MCP server not available', $toolCallId);
        }

        try {
            $result = $this->executeMCP($arguments);
            return $this->success($result, $toolCallId);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $toolCallId);
        }
    }
}
