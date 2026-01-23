<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Interfaces;

/**
 * Interface for MCP (Model Context Protocol) tools
 *
 * MCP tools are external tools that can be dynamically registered
 * and communicate via the Model Context Protocol specification.
 */
interface MCPToolInterface extends ToolInterface
{
    /**
     * Get the MCP server configuration
     *
     * @return array The MCP server config (command, args, env)
     */
    public function getMCPServerConfig(): array;

    /**
     * Get the MCP tool type/category
     *
     * @return string The tool category (e.g., 'filesystem', 'web', 'database')
     */
    public function getMCPCategory(): string;

    /**
     * Check if the MCP server is running and available
     *
     * @return bool True if the MCP server is accessible
     */
    public function isServerAvailable(): bool;
}
