<?php

namespace App\Console\Commands\Ai\Tools\Mcp;

use App\Models\Ai\McpServer;
use App\Services\Ai\Values\McpServerTimeouts;
use Illuminate\Console\Command;

class ListMcpServers extends Command
{
    protected $signature = 'ai:tools:mcp:list
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
            $this->formatTimeouts($s->timeouts),
            $s->tools_count,
            $s->api_key ? '***' : '—',
        ])->toArray();

        $this->newLine();
        $this->table(
            ['ID', 'Label', 'URL', 'Approval', 'Timeouts', 'Tools', 'API Key'],
            $rows
        );
        $this->newLine();

        return Command::SUCCESS;
    }

    private function formatTimeouts(McpServerTimeouts $t): string
    {
        $parts = [];
        if ($t->connectionTimeout !== null) {
            $parts[] = "connect={$t->connectionTimeout}s";
        }
        if ($t->readTimeout !== null) {
            $parts[] = "read={$t->readTimeout}s";
        }
        if ($t->sseIdleTimeout !== null) {
            $parts[] = "sse={$t->sseIdleTimeout}s";
        }
        return $parts !== [] ? implode(' ', $parts) : '—';
    }
}
