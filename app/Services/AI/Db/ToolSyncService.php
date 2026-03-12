<?php
declare(strict_types=1);

namespace App\Services\AI\Db;

use App\Models\Ai\Tools\AiTool;
use App\Models\Ai\Tools\McpServer;
use App\Services\AI\Tools\Interfaces\ToolInterface;
use Illuminate\Support\Facades\Log;

/**
 * Syncs tool configuration (config/tools.php) into the database.
 *
 * Function tools: config/tools.available_tools → ai_tools (type=function, class_name=...)
 * MCP servers:    config/tools.mcp_servers     → mcp_servers + ai_tools (type=mcp)
 *
 * The DB is the single source of truth at runtime.
 * Config is only read during deployment via this service.
 */
class ToolSyncService
{
    /**
     * Sync function-calling tools from config into ai_tools table.
     *
     * @return array{synced: int}
     */
    public function syncFunctionTools(): array
    {
        $synced = 0;

        foreach (config('tools.available_tools', []) as $class) {
            try {
                if (!class_exists($class)) {
                    Log::warning("ToolSyncService: class not found, skipping: {$class}");
                    continue;
                }

                /** @var ToolInterface $tool */
                $tool = app($class);

                AiTool::updateOrCreate(
                    ['name' => $tool->getName()],
                    [
                        'class_name'  => $class,
                        'description' => $tool->getDefinition()->description,
                        'inputSchema' => $tool->getDefinition()->parameters,
                        'capability'  => $tool->getCapability(),
                        'type'        => 'function',
                        'status'      => 'active',
                        'server_id'   => null,
                    ]
                );

                $synced++;
            } catch (\Exception $e) {
                Log::warning("ToolSyncService: failed to sync {$class}: " . $e->getMessage());
            }
        }

        return ['synced' => $synced];
    }

    /**
     * Sync MCP servers from config, connect to each, and persist discovered tools.
     *
     * Servers that are unreachable are skipped (not written to DB).
     * Tools whose capability is auto-generated from the tool name are returned as warnings.
     *
     * @return array{servers_synced: int, tools_synced: int, servers_failed: array, capability_warnings: array}
     */
    public function syncMcpServers(): array
    {
        $serversSynced      = 0;
        $toolsSynced        = 0;
        $serversFailed      = [];
        $capabilityWarnings = [];

        foreach (config('tools.mcp_servers', []) as $key => $cfg) {
            $url = $cfg['url'] ?? null;
            if (!$url) {
                $serversFailed[$key] = 'No URL configured';
                continue;
            }

            try {
                $server = McpServer::updateOrCreate(
                    ['url' => $url],
                    [
                        'server_label'      => $cfg['server_label'] ?? $key,
                        'description'       => $cfg['description'] ?? null,
                        'require_approval'  => $cfg['require_approval'] ?? 'never',
                        'timeout'           => $cfg['timeout'] ?? 30,
                        'discovery_timeout' => $cfg['discovery_timeout'] ?? 90,
                        'api_key'           => $cfg['api_key'] ?? null,
                    ]
                );

                $result = $server->fetchServerTools();

                if (!$result['success']) {
                    Log::warning("ToolSyncService: MCP server '{$key}' unreachable — " . ($result['message'] ?? 'unknown'));
                    $serversFailed[$key] = $result['message'] ?? 'unreachable';
                    continue;
                }

                foreach ($result['tools'] as $toolData) {
                    $server->setupTools($toolData); // capability auto-set to tool name
                    $capabilityWarnings[] = $toolData['name'];
                    $toolsSynced++;
                }

                $serversSynced++;
            } catch (\Exception $e) {
                Log::warning("ToolSyncService: failed to sync MCP server '{$key}': " . $e->getMessage());
                $serversFailed[$key] = $e->getMessage();
            }
        }

        return [
            'servers_synced'      => $serversSynced,
            'tools_synced'        => $toolsSynced,
            'servers_failed'      => $serversFailed,
            'capability_warnings' => $capabilityWarnings,
        ];
    }

    /**
     * Returns true when the ai_tools table has at least one row.
     */
    public function isSynced(): bool
    {
        try {
            return AiTool::exists();
        } catch (\Exception) {
            return false;
        }
    }
}
