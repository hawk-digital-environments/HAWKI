<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Interfaces;

/**
 * Interface for MCP (Model Context Protocol) tools
 *
 * MCP tools communicate with external MCP servers to execute their logic.
 * They can be used with:
 * - ExecutionStrategy::MCP - Model calls MCP server directly
 * - ExecutionStrategy::FUNCTION_CALL - HAWKI orchestrates via function calling
 */
interface MCPToolInterface extends ToolInterface
{
    /**
     * Get the MCP server configuration
     *
     * Must return an array with at least:
     * - 'url': The MCP server endpoint
     *
     * Optional keys:
     * - 'label': Human-readable server name
     * - 'require_approval': 'always', 'never', or 'prompt'
     * - 'timeout': Request timeout in seconds
     *
     * @return array The MCP server configuration
     */
    public function getMCPServerConfig(): array;

    /**
     * Check if the MCP server is running and available
     *
     * @return bool True if the MCP server is accessible
     */
    public function isServerAvailable(): bool;
}
