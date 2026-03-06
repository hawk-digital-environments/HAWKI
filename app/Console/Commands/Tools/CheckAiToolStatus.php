<?php

namespace App\Console\Commands\Tools;

use App\Models\Ai\Tools\McpServer;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAiToolStatus extends Command
{
    protected $signature = 'tools:check-status';

    protected $description = 'Check the reachability of all registered MCP servers and update the status of their tools accordingly.';

    public function handle(): int
    {
        $servers = McpServer::with('tools')->get();

        if ($servers->isEmpty()) {
            $this->info('No MCP servers registered.');
            return Command::SUCCESS;
        }

        $totalTools   = 0;
        $activeTools  = 0;
        $inactiveTools = 0;

        foreach ($servers as $server) {
            $tools = $server->tools;

            if ($tools->isEmpty()) {
                $this->line("  <fg=gray>→ {$server->server_label}</> — no tools registered, skipping.");
                continue;
            }

            $this->output->write("  Checking <fg=cyan>{$server->server_label}</> ({$server->url}) … ");

            try {
                $client      = new MCPSSEClient($server->url, (int) $server->timeout, $server->api_key ?: null);
                $isAvailable = $client->isAvailable();
            } catch (\Exception $e) {
                $isAvailable = false;
                Log::warning("MCP server '{$server->server_label}' check failed: " . $e->getMessage());
            }

            $newStatus = $isAvailable ? 'active' : 'inactive';

            // Update all tools for this server in one query
            $server->tools()->update(['status' => $newStatus]);

            $count = $tools->count();
            $totalTools += $count;

            if ($isAvailable) {
                $activeTools += $count;
                $this->line("<fg=green>online</> — {$count} tool(s) marked active");
            } else {
                $inactiveTools += $count;
                $this->line("<fg=red>offline</> — {$count} tool(s) marked inactive");
            }
        }

        $this->newLine();
        $this->info("Done. Checked {$servers->count()} server(s) / {$totalTools} tool(s): {$activeTools} active, {$inactiveTools} inactive.");

        return Command::SUCCESS;
    }
}
