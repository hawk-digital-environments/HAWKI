<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Implementations\DmcpTool;
use App\Services\AI\Tools\Implementations\TestTool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for registering AI tools
 */
class ToolServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // ToolRegistry is already a Singleton via attribute
        // No need to bind it here
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        $this->registerTools();
    }

    /**
     * Register all available tools
     */
    private function registerTools(): void
    {
        $registry = app(ToolRegistry::class);

        // Register built-in tools
        $this->registerBuiltInTools($registry);

        // Register MCP tools if configured
        $this->registerMCPTools($registry);

//        Log::info('AI Tools registered', [
//            'count' => count($registry->getAll()),
//        ]);
    }

    /**
     * Register built-in tools
     */
    private function registerBuiltInTools(ToolRegistry $registry): void
    {
        // Register TestTool
        $registry->register(new TestTool());

        // Add more built-in tools here as they are created
        // Example:
        // $registry->register(new WeatherTool());
        // $registry->register(new DatabaseQueryTool());
    }

    /**
     * Register MCP tools from configuration
     *
     * MCP tools are only available to models with 'mcp' => true in their configuration.
     * This ensures providers like OpenAI, Google, and Anthropic can use MCP tools,
     * while providers like GWDG (without MCP support) won't see them.
     */
    private function registerMCPTools(ToolRegistry $registry): void
    {
        // MCP server configurations
        // TODO: Move to config/ai.php for easier management
        $mcpServers = [
            [
                'class' => DmcpTool::class,
                'server_url' => 'https://dmcp-server.deno.dev/sse',
                'server_label' => 'dmcp',
                'server_description' => 'D&D dice rolling server',
            ],
        ];

        foreach ($mcpServers as $serverConfig) {
            try {
                $toolClass = $serverConfig['class'] ?? null;

                if ($toolClass && class_exists($toolClass)) {
                    $tool = new $toolClass($serverConfig);
                    $registry->register($tool);
                }
            } catch (\Exception $e) {
                Log::error('Failed to register MCP tool', [
                    'config' => $serverConfig,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
