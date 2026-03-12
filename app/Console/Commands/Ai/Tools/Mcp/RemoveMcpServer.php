<?php

namespace App\Console\Commands\Ai\Tools\Mcp;

use App\Models\Ai\Tools\McpServer;
use Illuminate\Console\Command;

class RemoveMcpServer extends Command
{
    protected $signature = 'ai:tools:mcp:remove
                            {id? : The ID of the MCP server to remove}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Remove an MCP server and all its associated tools';

    public function handle(): int
    {
        $serverId = $this->argument('id');

        if (!$serverId) {
            $servers = McpServer::withCount('tools')->get();

            if ($servers->isEmpty()) {
                $this->warn('No MCP servers registered.');
                return Command::SUCCESS;
            }

            $choices = $servers->map(fn($s) => "[{$s->id}] {$s->server_label} ({$s->url})")->toArray();
            $selected = $this->choice('Select the MCP server to remove', $choices);
            preg_match('/^\[(\d+)\]/', $selected, $matches);
            $serverId = $matches[1] ?? null;
        }

        $server = McpServer::with('tools')->find($serverId);

        if (!$server) {
            $this->error("MCP server with ID {$serverId} not found.");
            return Command::FAILURE;
        }

        $toolCount = $server->tools->count();
        $this->warn("You are about to remove:");
        $this->line("  Server:  {$server->server_label} (ID: {$server->id})");
        $this->line("  URL:     {$server->url}");
        $this->line("  Tools:   {$toolCount} tool(s) will also be deleted");

        if (!$this->option('force') && !$this->confirm('Are you sure?', false)) {
            $this->info('Aborted.');
            return Command::SUCCESS;
        }

        // Cascade delete: ai_tools → ai_model_tools are handled by DB constraints
        $server->delete();

        $this->info("  ✓ MCP server '{$server->server_label}' and {$toolCount} tool(s) removed.");

        return Command::SUCCESS;
    }
}
