<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Interfaces\MCPToolInterface;
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

//                Log::debug("Registered tool: {$tool->getName()}", [
//                    'class' => $toolClass,
//                    'is_mcp' => $tool instanceof MCPToolInterface,
//                ]);
            } catch (\Exception $e) {
                Log::error("Failed to register tool: {$toolClass}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
}
