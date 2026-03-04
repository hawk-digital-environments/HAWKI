<?php
declare(strict_types=1);

namespace App\Providers;

use App\Models\Ai\Tools\AiTool;
use App\Services\AI\Tools\Implementations\DynamicMCPTool;
use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\Registry\McpToolDiscoveryHandler;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Tools\Value\ToolDefinition;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for registering AI tools.
 *
 * Loading order:
 * 1. Class-based tools listed in config/tools.php  (backward compatible)
 * 2. DB-backed MCP tools stored in the ai_tools table  (new, DB-first)
 *
 * The old config/tools.mcp_servers discovery path is kept as a fallback
 * so that existing deployments that haven't migrated yet still work.
 */
class ToolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ToolRegistry is already a Singleton via its #[Singleton] attribute.
    }

    public function boot(): void
    {
        $this->registerTools();
    }

    private function registerTools(): void
    {
        $registry = app(ToolRegistry::class);

        // 1. Class-based function-calling tools (backward compatible)
        $this->registerClassBasedTools($registry);

        // 2. MCP tools stored in the database (primary path for new installs)
        $this->registerDbMcpTools($registry);

        // 3. Config-based MCP server discovery (legacy fallback)
        //    Skipped when DB tools are already registered for the same servers
        //    to avoid duplicate registrations.
        $this->registerConfigMcpTools($registry);
    }

    /**
     * Register tools whose class is listed in config/tools.available_tools.
     */
    private function registerClassBasedTools(ToolRegistry $registry): void
    {
        $mcpServers = config('tools.mcp_servers', []);

        foreach (config('tools.available_tools', []) as $toolClass) {
            try {
                if (!class_exists($toolClass)) {
                    Log::warning("Tool class not found: {$toolClass}");
                    continue;
                }

                if (is_subclass_of($toolClass, MCPToolInterface::class)) {
                    $tool = $this->instantiateMCPTool($toolClass, $mcpServers);
                } else {
                    $tool = app($toolClass);
                }

                $registry->register($tool);
            } catch (\Exception $e) {
                Log::error("Failed to register tool: {$toolClass}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Load active MCP tools stored in the ai_tools table and register them.
     * Each tool gets a DynamicMCPTool wrapper that proxies calls to its McpServer.
     */
    private function registerDbMcpTools(ToolRegistry $registry): void
    {
        try {
            $tools = AiTool::with('server')
                ->active()
                ->mcp()
                ->get();

            foreach ($tools as $tool) {
                if (!$tool->server) {
                    Log::warning("AiTool '{$tool->name}' has no associated MCP server, skipping.");
                    continue;
                }

                $server = $tool->server;

                $definition = new ToolDefinition(
                    name: $tool->name,
                    description: $tool->description ?? '',
                    parameters: $tool->inputSchema ?? ['type' => 'object', 'properties' => []]
                );

                // Derive the raw MCP tool name by stripping the server_label prefix.
                $prefix      = $server->server_label . '-';
                $mcpToolName = str_starts_with($tool->name, $prefix)
                    ? substr($tool->name, strlen($prefix))
                    : $tool->name;

                $serverConfig = [
                    'url'              => $server->url,
                    'server_label'     => $server->server_label,
                    'require_approval' => $server->require_approval,
                    'timeout'          => (int) $server->timeout,
                    'api_key'          => $server->api_key ?: null,
                ];

                $registry->register(new DynamicMCPTool($tool->name, $definition, $mcpToolName, $serverConfig));
            }
        } catch (\Exception $e) {
            // DB may not be available yet (e.g. during initial migrate).
            Log::debug('Could not load DB MCP tools: ' . $e->getMessage());
        }
    }

    /**
     * Discover MCP tools from config/tools.mcp_servers (legacy path).
     * Only runs when a server's tools are NOT already registered via the DB path,
     * preventing duplicates.
     */
    private function registerConfigMcpTools(ToolRegistry $registry): void
    {
        $mcpServers = config('tools.mcp_servers', []);
        if (empty($mcpServers)) {
            return;
        }

        $discoveryHandler = app(McpToolDiscoveryHandler::class);
        $discoveryHandler->discoverAndRegisterMCPTools($registry, $mcpServers);
    }

    private function instantiateMCPTool(string $toolClass, array $mcpServers): MCPToolInterface
    {
        $tempTool   = app($toolClass, ['serverConfig' => []]);
        $serverConfig = $mcpServers[$tempTool->getName()] ?? [];
        return app($toolClass, ['serverConfig' => $serverConfig]);
    }
}
