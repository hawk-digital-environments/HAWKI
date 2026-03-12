<?php

namespace App\Console\Commands\Tools\Mcp;

use App\Models\Ai\Tools\McpServer;
use Illuminate\Console\Command;

class ListMcpServers extends Command
{
    protected $signature = 'tools:list-mcp-servers
                            {--json : Output as JSON}';

    protected $description = 'List all registered MCP servers';

    public function handle(): int
    {
        $servers = McpServer::withCount('tools')->get();

        if ($servers->isEmpty()) {
            $this->warn('No MCP servers registered. Use <comment>php artisan tools:add-mcp-server</comment> to add one.');
            return Command::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($servers->toJson(JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $rows = $servers->map(fn($s) => [
            $s->id,
            $s->server_label,
            $s->url,
            $s->require_approval,
            "{$s->timeout}s",
            $s->tools_count,
            $s->api_key ? '***' : '—',
        ])->toArray();

        $this->newLine();
        $this->table(
            ['ID', 'Label', 'URL', 'Approval', 'Timeout', 'Tools', 'API Key'],
            $rows
        );
        $this->newLine();

        return Command::SUCCESS;
    }
}
