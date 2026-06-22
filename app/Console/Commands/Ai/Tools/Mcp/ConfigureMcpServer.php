<?php

namespace App\Console\Commands\Ai\Tools\Mcp;

use App\Models\Ai\McpServer;
use App\Services\Ai\Values\McpServerTimeouts;
use Illuminate\Console\Command;

class ConfigureMcpServer extends Command
{
    protected $signature = 'ai:tools:mcp:configure
                            {--server= : Server label or ID to configure (skips interactive selection)}';

    protected $description = 'Configure an MCP server\'s attributes (URL, label, timeouts, API key, etc.)';

    public function handle(): int
    {
        $server = $this->resolveServer();

        if (!$server) {
            return Command::FAILURE;
        }

        $this->newLine();
        $this->showServerSummary($server);
        $this->newLine();

        $changed = false;

        // ── URL ───────────────────────────────────────────────────────────────
        $this->line("  Current URL: <fg=cyan>{$server->url}</>");
        if ($this->confirm("Update URL?", false)) {
            $newUrl = $this->ask('Enter new URL', $server->url);
            if ($newUrl && $newUrl !== $server->url) {
                $server->url = $newUrl;
                $changed = true;
                $this->info("  ✓ URL → <fg=cyan>{$newUrl}</>");
                $this->warn("  ⚠ URL changed — existing tools may no longer match this server.");
                $this->line("    Run <comment>php artisan ai:tools:sync --mcp-only --force</comment> to re-discover tools.");
            }
        }

        // ── Label ─────────────────────────────────────────────────────────────
        $this->line("  Current label: <fg=cyan>{$server->server_label}</>");
        if ($this->confirm("Update label?", false)) {
            $newLabel = $this->ask('Enter new label', $server->server_label);
            if ($newLabel && $newLabel !== $server->server_label) {
                $server->server_label = $newLabel;
                $changed = true;
                $this->info("  ✓ Label → <fg=cyan>{$newLabel}</>");
            }
        }

        // ── Description ───────────────────────────────────────────────────────
        $this->line("  Current description: <fg=gray>{$server->description}</>");
        if ($this->confirm("Update description?", false)) {
            $desc = $this->ask('Enter new description', $server->description);
            if ($desc !== null && $desc !== $server->description) {
                $server->description = $desc;
                $changed = true;
                $this->info("  ✓ Description updated.");
            }
        }

        // ── Require approval ─────────────────────────────────────────────────
        $this->line("  Current require_approval: <fg=cyan>{$server->require_approval}</>");
        if ($this->confirm("Update require_approval?", false)) {
            $choice = $this->choice(
                'When should approval be required?',
                ['never', 'always', 'auto'],
                $server->require_approval
            );
            if ($choice !== $server->require_approval) {
                $server->require_approval = $choice;
                $changed = true;
                $this->info("  ✓ require_approval → <fg=cyan>{$choice}</>");
            }
        }

        // ── Timeouts ──────────────────────────────────────────────────────────
        $this->line("  Current timeouts: <fg=cyan>{$this->formatTimeouts($server->timeouts)}</>");
        if ($this->confirm("Update timeouts?", false)) {
            $currentRead = $server->timeouts->readTimeout;
            $currentConnect = $server->timeouts->connectionTimeout;
            $currentSse = $server->timeouts->sseIdleTimeout;

            $readVal = $this->ask('Read timeout in seconds (blank to clear)', $currentRead !== null ? (string)$currentRead : null);
            $connectVal = $this->ask('Connection timeout in seconds (blank to clear)', $currentConnect !== null ? (string)$currentConnect : null);
            $sseVal = $this->ask('SSE idle timeout in seconds (blank to clear)', $currentSse !== null ? (string)$currentSse : null);

            $newRead = ($readVal !== null && $readVal !== '') ? (float)$readVal : null;
            $newConnect = ($connectVal !== null && $connectVal !== '') ? (float)$connectVal : null;
            $newSse = ($sseVal !== null && $sseVal !== '') ? (float)$sseVal : null;

            $server->timeouts = new McpServerTimeouts(
                readTimeout: $newRead,
                connectionTimeout: $newConnect,
                sseIdleTimeout: $newSse
            );
            $changed = true;
            $this->info("  ✓ Timeouts → <fg=cyan>{$this->formatTimeouts($server->timeouts)}</>");
        }

        // ── API key ───────────────────────────────────────────────────────────
        // Show a masked hint rather than the raw key.
        $maskedKey = $this->maskApiKey($server->api_key);
        $this->line("  Current API key: <fg=gray>{$maskedKey}</>");
        if ($this->confirm("Update API key?", false)) {
            $newKey = $this->secret('Enter new API key (leave blank to clear)');
            $newKey = ($newKey === null || $newKey === '') ? null : $newKey;

            // Always update — even if it looks the same we can't compare encrypted values easily
            $server->api_key = $newKey;
            $changed = true;
            $this->info($newKey ? '  ✓ API key updated (stored encrypted).' : '  ✓ API key cleared.');
        }

        if (!$changed) {
            $this->line('  No changes made.');
            return Command::SUCCESS;
        }

        $server->save();

        $this->newLine();
        $this->info('✓ Server updated successfully.');

        return Command::SUCCESS;
    }

    private function resolveServer(): ?McpServer
    {
        $search = $this->option('server');

        if ($search) {
            $server = McpServer::where('id', $search)
                ->orWhere('server_label', $search)
                ->first();

            if (!$server) {
                $this->error("Server '{$search}' not found.");
                return null;
            }

            return $server;
        }

        $servers = McpServer::withCount('tools')->orderBy('server_label')->get();

        if ($servers->isEmpty()) {
            $this->warn('No MCP servers registered. Run <comment>php artisan tools:add-mcp-server</comment> first.');
            return null;
        }

        $labels = $servers->map(fn($s) => "{$s->server_label} (id:{$s->id}, {$s->tools_count} tools)")->toArray();
        $chosen = $this->choice('Select a server to configure', $labels);
        $index = array_search($chosen, $labels);

        return $servers[$index] ?? null;
    }

    private function showServerSummary(McpServer $server): void
    {
        $this->line("  <fg=magenta;options=bold>{$server->server_label}</> (id: {$server->id})");
        $this->line("  URL:                <fg=cyan>{$server->url}</>");
        $this->line("  Description:        " . ($server->description ?: '<fg=gray>—</>'));
        $this->line("  Require approval:   {$server->require_approval}");
        $this->line("  Timeouts:           {$this->formatTimeouts($server->timeouts)}");
        $this->line("  API key:            {$this->maskApiKey($server->api_key)}");
        $toolCount = $server->tools()->count();
        $this->line("  Tools:              {$toolCount} registered");
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

    /**
     * Return a masked representation of the key for display.
     * Never shows the real value — just confirms whether one is set.
     */
    private function maskApiKey(?string $key): string
    {
        if (empty($key)) {
            return '<fg=gray>not set</>';
        }

        // Show first 4 chars + stars to confirm something is stored
        $visible = mb_strlen($key) > 4 ? mb_substr($key, 0, 4) : '****';
        return $visible . str_repeat('*', 12) . ' <fg=gray>(set, stored encrypted)</>';
    }
}
