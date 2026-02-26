<?php

namespace App\Console\Commands\Tools\Mcp;

use App\Services\AI\Tools\MCP\MCPSSEClient;
use Illuminate\Console\Command;

class DiscoverMCPTools extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tools:discover {--force : Force re-discovery even if cache exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and cache tools from MCP servers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Discovering tools from MCP servers...');
        $this->newLine();

        $cachePath = storage_path('framework/cache/mcp-tools.php');
        $force = $this->option('force');

        // Clear cache if force option is used
        if ($force && file_exists($cachePath)) {
            unlink($cachePath);
            $this->info('✓ Cleared existing cache');
        }

        // Get MCP server configurations
        $mcpServers = config('tools.mcp_servers', []);

        if (empty($mcpServers)) {
            $this->warn('No MCP servers configured in config/tools.php');
            return Command::SUCCESS;
        }

        $discoveredTools = [];
        $totalTools = 0;

        foreach ($mcpServers as $serverKey => $serverConfig) {
            $serverUrl = $serverConfig['url'] ?? null;
            if (!$serverUrl) {
                $this->error("✗ Server {$serverKey} has no URL configured");
                continue;
            }

            $this->line("→ Connecting to <comment>{$serverKey}</comment> ({$serverUrl})...");

            try {
                $timeout = $serverConfig['discovery_timeout'] ?? 5;
                $apiKey = $serverConfig['api_key'] ?? null;
                $client = new MCPSSEClient($serverUrl, $timeout, $apiKey);

                // Check server availability
                if (!$client->isAvailable()) {
                    $this->error("  ✗ Server not available");
                    continue;
                }

                // List tools from server
                $response = $client->listTools();
                $tools = $response['tools'] ?? [];

                if (empty($tools)) {
                    $this->warn("  ⊗ No tools found");
                    continue;
                }

                $serverLabel = $serverConfig['server_label'] ?? $serverKey;
                $toolCount = count($tools);

                $this->info("  ✓ Found {$toolCount} tools:");

                foreach ($tools as $toolInfo) {
                    $toolName = $toolInfo['name'] ?? 'unknown';
                    $prefixedName = "{$serverLabel}-{$toolName}";
                    $description = $toolInfo['description'] ?? 'No description';

                    $this->line("    • <fg=cyan>{$prefixedName}</> - {$description}");

                    $discoveredTools[] = [
                        'name' => $prefixedName,
                        'mcp_tool_name' => $toolName,
                        'server_key' => $serverKey,
                        'description' => $description,
                    ];
                }

                $totalTools += $toolCount;
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: " . $e->getMessage());
            }

            $this->newLine();
        }

        // Show summary
        $this->newLine();
        if ($totalTools > 0) {
            $this->info("✓ Successfully discovered {$totalTools} tools from " . count($mcpServers) . " servers");
            $this->info("Cache will be refreshed on next application boot");
        } else {
            $this->warn('No tools were discovered');
        }

        return Command::SUCCESS;
    }
}
