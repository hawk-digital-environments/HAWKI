<?php

namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\AbstractMCPTool;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\Value\ToolDefinition;

/**
 * Generic MCP tool implementation that uses dynamically discovered tool definitions
 * from MCP servers. This allows adding new MCP tools without creating dedicated classes.
 */
class DynamicMCPTool extends AbstractMCPTool
{
    /**
     * The tool name as registered in the system (prefixed with server label)
     */
    private string $toolName;

    /**
     * The tool definition from the MCP server
     */
    private ToolDefinition $definition;

    /**
     * The original tool name on the MCP server
     */
    private string $mcpToolName;

    /**
     * The MCP server configuration
     */
    private array $serverConfig;

    /**
     * Create a new dynamic MCP tool instance
     *
     * @param string $toolName The tool name for registration (e.g., "rawki_search.search-tool")
     * @param ToolDefinition $definition The tool definition from MCP server
     * @param string $mcpToolName The actual tool name on the MCP server
     * @param array $serverConfig The MCP server configuration
     */
    public function __construct(
        string $toolName,
        ToolDefinition $definition,
        string $mcpToolName,
        array $serverConfig
    ) {
        $this->toolName = $toolName;
        $this->definition = $definition;
        $this->mcpToolName = $mcpToolName;
        $this->serverConfig = $serverConfig;
    }

    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return $this->toolName;
    }

    /**
     * Get the tool definition
     */
    public function getDefinition(): ToolDefinition
    {
        return $this->definition;
    }

    /**
     * Get MCP server configuration
     */
    public function getMCPServerConfig(): array
    {
        return $this->serverConfig;
    }

    /**
     * Execute the tool by calling the MCP server
     *
     * @param array $arguments The tool arguments
     * @return mixed The tool execution result
     */
    protected function executeMCP(array $arguments): mixed
    {
        $serverUrl = $this->serverConfig['url'] ?? throw new \RuntimeException('MCP server URL not configured');
        $timeout = $this->serverConfig['timeout'] ?? 30;
        $apiKey = $this->serverConfig['api_key'] ?? null;

        $client = new MCPSSEClient($serverUrl, $timeout, $apiKey);
        $response = $client->callTool($this->mcpToolName, $arguments);
//        \Log::debug('REPSONSE: ');
//        \Log::debug($response);
        // Return the result directly, or the full response if no result field
        return $response['result'] ?? $response;
    }
}
