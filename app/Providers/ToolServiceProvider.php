<?php
declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\Tools\Implementations\DynamicMCPTool;
use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\Registry\McpToolDiscoveryHandler;
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
                    Log::warning("AiTool class not found: {$toolClass}");
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
        $discoveryHandler = app(McpToolDiscoveryHandler::class);
        $discoveryHandler->discoverAndRegisterMCPTools($registry, $mcpServers);
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
}
