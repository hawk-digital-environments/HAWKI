<?php

namespace App\Services\AI\Tools\Registry;

use App\Services\AI\Tools\Implementations\DynamicMCPTool;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Tools\Value\ToolDefinition;
use Exception;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
class McpToolDiscoveryHandler
{
    /**
     * Discover tools from MCP servers and register them dynamically
     */
    public function discoverAndRegisterMCPTools(ToolRegistry $registry, array $mcpServers): void
    {
        $cachePath = storage_path('framework/cache/mcp-tools.php');

        // Try to load from cache
        $cacheHandler = app(ToolCacheHandler::class);
        $discoveredTools = $cacheHandler->loadToolsFromCache($cachePath);

        if ($discoveredTools === null) {
            // Cache miss or expired - discover from servers
            $discoveredTools = $this->discoverToolsFromServers($mcpServers);
            $cacheHandler->saveToolsToCache($cachePath, $discoveredTools);
        }

        // Register discovered tools
        foreach ($discoveredTools as $toolData) {
            try {
                $tool = new DynamicMCPTool(
                    $toolData['name'],
                    $toolData['definition'],
                    $toolData['mcp_tool_name'],
                    $toolData['server_config']
                );
                $registry->register($tool);

            } catch (Exception $e) {
                Log::error("Failed to register dynamic MCP tool: {$toolData['name']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    /**
     * Discover tools from all configured MCP servers
     */
    private function discoverToolsFromServers(array $mcpServers): array
    {
        $discoveredTools = [];

        foreach ($mcpServers as $serverKey => $serverConfig) {
            try {
                $serverUrl = $serverConfig['url'] ?? null;
                if (!$serverUrl) {
                    Log::warning("MCP server $serverKey has no URL configured");
                    continue;
                }

                $timeout = $serverConfig['discovery_timeout'] ?? 5;
                $apiKey = $serverConfig['api_key'] ?? null;
                $client = new MCPSSEClient($serverUrl, $timeout, $apiKey);

                // Check server availability
                if (!$client->isAvailable()) {
                    Log::warning("MCP server not available: $serverKey at $serverUrl");
                    continue;
                }

                // List tools from the server
                $response = $client->listTools();
                $tools = $response['tools'] ?? [];

                if (empty($tools)) {
                    Log::info("No tools found on MCP server: $serverKey");
                    continue;
                }

                $serverLabel = $serverConfig['server_label'] ?? $serverKey;

                foreach ($tools as $toolInfo) {
                    $mcpToolName = $toolInfo['name'] ?? null;
                    if (!$mcpToolName) {
                        Log::warning("AiTool without name from MCP server: $serverKey");
                        continue;
                    }

                    // Prefix tool name with server label to avoid conflicts
                    $toolName = "$serverLabel-$mcpToolName";

                    $discoveredTools[] = [
                        'name' => $toolName,
                        'definition' => $this->convertToToolDefinition($toolInfo, $toolName),
                        'mcp_tool_name' => $mcpToolName,
                        'server_config' => $serverConfig,
                        'server_key' => $serverKey,
                    ];
                }

            } catch (Exception $e) {
                Log::warning("Failed to discover tools from MCP server $serverKey: {$e->getMessage()}");
            }
        }

        return $discoveredTools;
    }


    /**
     * Convert MCP tool info to ToolDefinition
     */
    private function convertToToolDefinition(array $toolInfo, string $toolName): ToolDefinition
    {
        $description = $toolInfo['description'] ?? "AiTool from MCP server";
        $inputSchema = $toolInfo['inputSchema'] ?? ['type' => 'object', 'properties' => []];

        return new ToolDefinition(
            name: $toolName,
            description: $description,
            parameters: $inputSchema
        );
    }
}
