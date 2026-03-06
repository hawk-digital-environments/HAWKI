<?php
declare(strict_types=1);

namespace App\Providers;

use App\Models\Ai\Tools\AiTool;
use App\Services\AI\Tools\Implementations\DynamicMCPTool;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Tools\Value\ToolDefinition;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Loads all active tools from the database into the ToolRegistry at boot.
 *
 * The database is the single source of truth at runtime.
 * Config files (config/tools.php) are only read during deployment via `tools:sync`.
 */
class ToolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ToolRegistry is already a Singleton via its #[Singleton] attribute.
    }

    public function boot(): void
    {
        $this->loadDbTools(app(ToolRegistry::class));
    }

    private function loadDbTools(ToolRegistry $registry): void
    {
        try {
            // Always respect the user's active toggle.
            // Optionally also filter by server reachability status.
            $query = AiTool::with('server')->where('active', true);

            if (config('tools.check_tool_status', true)) {
                $query->active(); // also requires status = 'active'
            }

            foreach ($query->get() as $tool) {
                match ($tool->type) {
                    'function' => $this->registerFunctionTool($tool, $registry),
                    'mcp'      => $this->registerMcpTool($tool, $registry),
                    default    => null,
                };
            }
        } catch (\Exception $e) {
            // DB may not be available yet (e.g. during initial migrate).
            Log::debug('Could not load DB tools: ' . $e->getMessage());
        }
    }

    private function registerFunctionTool(AiTool $tool, ToolRegistry $registry): void
    {
        $class = $tool->class_name;

        if (!$class || !class_exists($class)) {
            Log::warning("ToolServiceProvider: class_name missing or not found for tool '{$tool->name}' ({$class}), skipping.");
            return;
        }

        try {
            $registry->register(app($class));
        } catch (\Exception $e) {
            Log::error("ToolServiceProvider: failed to register function tool '{$tool->name}': " . $e->getMessage());
        }
    }

    private function registerMcpTool(AiTool $tool, ToolRegistry $registry): void
    {
        if (!$tool->server) {
            Log::warning("ToolServiceProvider: AiTool '{$tool->name}' has no associated MCP server, skipping.");
            return;
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
}
