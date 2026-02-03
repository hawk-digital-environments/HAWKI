<?php
declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\Tools\Implementations\DynamicMCPTool;
use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Tools\Value\ToolDefinition;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for registering AI tools
 *
 * Reads tool configurations from config/tools.php and registers them in the ToolRegistry.
 */
class ToolServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // ToolRegistry is already a Singleton via attribute
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        $this->registerTools();
    }

    /**
     * Register all available tools from configuration
     */
    private function registerTools(): void
    {
        $registry = app(ToolRegistry::class);
        $toolClasses = config('tools.available_tools', []);
        $mcpServers = config('tools.mcp_servers', []);

        // Register class-based tools
        foreach ($toolClasses as $toolClass) {
            try {
                if (!class_exists($toolClass)) {
                    Log::warning("Tool class not found: {$toolClass}");
                    continue;
                }

                // Check if it's an MCP tool
                if (is_subclass_of($toolClass, MCPToolInterface::class)) {
                    $tool = $this->instantiateMCPTool($toolClass, $mcpServers);
                } else {
                    $tool = app($toolClass);
                }

                $registry->register($tool);
            } catch (\Exception $e) {
                Log::error("Failed to register tool: {$toolClass}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Discover and register dynamic MCP tools
        $this->discoverAndRegisterMCPTools($registry, $mcpServers);

//        Log::info('AI Tools registered', [
//            'total_count' => count($registry->getAll()),
//            'mcp_count' => count($registry->getMCPTools()),
//        ]);
    }

    /**
     * Instantiate an MCP tool with its server configuration
     */
    private function instantiateMCPTool(string $toolClass, array $mcpServers): MCPToolInterface
    {
        // Create a temporary instance to get the tool name
        $tempTool = app($toolClass, ['serverConfig' => []]);
        $toolName = $tempTool->getName();

        // Get server config for this tool
        $serverConfig = $mcpServers[$toolName] ?? [];

        // Create the actual tool instance with proper config
        return app($toolClass, ['serverConfig' => $serverConfig]);
    }

    /**
     * Discover tools from MCP servers and register them dynamically
     */
    private function discoverAndRegisterMCPTools(ToolRegistry $registry, array $mcpServers): void
    {
        $cachePath = storage_path('framework/cache/mcp-tools.php');

        // Try to load from cache
        $discoveredTools = $this->loadToolsFromCache($cachePath);

        if ($discoveredTools === null) {
            // Cache miss or expired - discover from servers
            $discoveredTools = $this->discoverToolsFromServers($mcpServers);
            $this->saveToolsToCache($cachePath, $discoveredTools);
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

//                Log::debug("Registered dynamic MCP tool: {$toolData['name']}", [
//                    'server' => $toolData['server_key'],
//                ]);
            } catch (\Exception $e) {
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
                    Log::warning("MCP server {$serverKey} has no URL configured");
                    continue;
                }

                $timeout = $serverConfig['discovery_timeout'] ?? 5;
                $apiKey = $serverConfig['api_key'] ?? null;
                $client = new MCPSSEClient($serverUrl, $timeout, $apiKey);

                // Check server availability
                if (!$client->isAvailable()) {
                    Log::warning("MCP server not available: {$serverKey} at {$serverUrl}");
                    continue;
                }

                // List tools from the server
                $response = $client->listTools();
                $tools = $response['tools'] ?? [];

                if (empty($tools)) {
                    Log::info("No tools found on MCP server: {$serverKey}");
                    continue;
                }

                $serverLabel = $serverConfig['server_label'] ?? $serverKey;

                foreach ($tools as $toolInfo) {
                    $mcpToolName = $toolInfo['name'] ?? null;
                    if (!$mcpToolName) {
                        Log::warning("Tool without name from MCP server: {$serverKey}");
                        continue;
                    }

                    // Prefix tool name with server label to avoid conflicts
                    $toolName = "{$serverLabel}-{$mcpToolName}";

                    $discoveredTools[] = [
                        'name' => $toolName,
                        'definition' => $this->convertToToolDefinition($toolInfo, $toolName),
                        'mcp_tool_name' => $mcpToolName,
                        'server_config' => $serverConfig,
                        'server_key' => $serverKey,
                    ];
                }

//                Log::info("Discovered " . count($tools) . " tools from MCP server: {$serverKey}", [
//                    'server' => $serverKey,
//                    'url' => $serverUrl,
//                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to discover tools from MCP server {$serverKey}: {$e->getMessage()}");
            }
        }

        return $discoveredTools;
    }

    /**
     * Convert MCP tool info to ToolDefinition
     */
    private function convertToToolDefinition(array $toolInfo, string $toolName): ToolDefinition
    {
        $description = $toolInfo['description'] ?? "Tool from MCP server";
        $inputSchema = $toolInfo['inputSchema'] ?? ['type' => 'object', 'properties' => []];

        return new ToolDefinition(
            name: $toolName,
            description: $description,
            parameters: $inputSchema
        );
    }

    /**
     * Load discovered tools from cache
     *
     * @return array|null Array of tool data or null if cache miss
     */
    private function loadToolsFromCache(string $cachePath): ?array
    {
        if (!file_exists($cachePath)) {
            return null;
        }

        try {
            $cached = require $cachePath;

            // Validate cache structure
            if (!is_array($cached) || !isset($cached['version'], $cached['timestamp'], $cached['tools'])) {
                Log::warning('Invalid MCP tools cache structure, will rebuild');
                return null;
            }

            // Check cache age (default: 1 hour)
            $maxAge = config('tools.mcp_cache_ttl', 3600);
            if (time() - $cached['timestamp'] > $maxAge) {
                Log::debug('MCP tools cache expired, will rebuild');
                return null;
            }

            // Reconstruct ToolDefinition objects
            foreach ($cached['tools'] as &$toolData) {
                $def = $toolData['definition'];
                $toolData['definition'] = new ToolDefinition(
                    name: $def['name'],
                    description: $def['description'],
                    parameters: $def['parameters']
                );
            }

//            Log::debug('Loaded MCP tools from cache', [
//                'count' => count($cached['tools']),
//                'age' => time() - $cached['timestamp'],
//            ]);

            return $cached['tools'];
        } catch (\Exception $e) {
            Log::warning('Failed to load MCP tools cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Save discovered tools to cache
     */
    private function saveToolsToCache(string $cachePath, array $tools): void
    {
        try {
            // Ensure directory exists
            $dir = dirname($cachePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Prepare data for caching (serialize ToolDefinition)
            $cacheData = [
                'version' => 1,
                'timestamp' => time(),
                'tools' => array_map(function ($toolData) {
                    return [
                        'name' => $toolData['name'],
                        'definition' => [
                            'name' => $toolData['definition']->name,
                            'description' => $toolData['definition']->description,
                            'parameters' => $toolData['definition']->parameters,
                        ],
                        'mcp_tool_name' => $toolData['mcp_tool_name'],
                        'server_config' => $toolData['server_config'],
                        'server_key' => $toolData['server_key'],
                    ];
                }, $tools),
            ];

            // Write cache file
            $content = '<?php return ' . var_export($cacheData, true) . ';';
            file_put_contents($cachePath, $content, LOCK_EX);

//            Log::debug('Saved MCP tools to cache', [
//                'count' => count($tools),
//                'path' => $cachePath,
//            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to save MCP tools cache: ' . $e->getMessage());
        }
    }
}
