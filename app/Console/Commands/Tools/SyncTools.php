<?php

namespace App\Console\Commands\Tools;

use App\Services\AI\Db\ToolSyncService;
use Illuminate\Console\Command;

class SyncTools extends Command
{
    protected $signature = 'tools:sync
                            {--force         : Re-sync even if tools already exist in DB}
                            {--function-only : Only sync function-calling tools}
                            {--mcp-only      : Only sync MCP servers}';

    protected $description = 'Sync tools from config files into the database (deployment-time operation)';

    public function handle(ToolSyncService $syncService): int
    {
        $functionOnly = $this->option('function-only');
        $mcpOnly      = $this->option('mcp-only');

        if (!$this->option('force') && $syncService->isSynced()) {
            $this->info('Tools are already synced. Use --force to re-sync.');
            return Command::SUCCESS;
        }

        $this->info('Syncing tools from config into database…');
        $this->newLine();

        if (!$mcpOnly) {
            $this->line('→ Syncing function-calling tools…');
            $stats = $syncService->syncFunctionTools();
            $this->info("  ✓ Function tools synced: {$stats['synced']}");
            $this->newLine();
        }

        if (!$functionOnly) {
            $this->line('→ Syncing MCP servers…');
            $stats = $syncService->syncMcpServers();
            $this->info("  ✓ Servers synced: {$stats['servers_synced']}");
            $this->info("  ✓ MCP tools synced: {$stats['tools_synced']}");

            if (!empty($stats['servers_failed'])) {
                $this->newLine();
                $this->warn('  Servers that could not be reached (skipped):');
                foreach ($stats['servers_failed'] as $key => $reason) {
                    $this->warn("    ✗ {$key}: {$reason}");
                }
            }

            if (!empty($stats['capability_warnings'])) {
                $this->newLine();
                $this->warn('  ⚠ The following MCP tools were synced with auto-generated capabilities');
                $this->warn('    (the tool name was used as the capability key). Review and update them:');
                foreach ($stats['capability_warnings'] as $toolName) {
                    $this->line("      • <fg=yellow>{$toolName}</>");
                }
                $this->newLine();
                $this->line("  → Run <comment>php artisan tools:configure</comment> to set meaningful capability keys.");
            }

            $this->newLine();
        }

        $this->info('Done. Run <comment>php artisan tools:list</comment> to review the results.');

        return Command::SUCCESS;
    }
}
