<?php

namespace App\Console\Commands\Ai\Tools;

use App\Services\Ai\Tools\FunctionToolSyncer;
use App\Services\Ai\Tools\Mcp\McpToolSyncer;
use Illuminate\Console\Command;

class SyncTools extends Command
{
    protected $signature = 'ai:tools:sync
                            {--function-only : Only sync function-calling tools}
                            {--mcp-only      : Only sync MCP servers}';

    protected $description = 'Sync tools from config files into the database (deployment-time operation)';

    public function handle(
        FunctionToolSyncer $functionToolSyncer,
        McpToolSyncer      $mcpToolSyncer,
    ): int
    {
        $functionOnly = $this->option('function-only');
        $mcpOnly = $this->option('mcp-only');

        $this->info('Syncing tools from config into database…');
        $this->newLine();

        $metrics = null;

        if (!$mcpOnly) {
            $this->line('→ Syncing function-calling tools…');
            $metrics = $functionToolSyncer->sync();
            if ($metrics->hasErrors()) {
                $this->warn('  Some function tools could not be synced. Check logs for details.');
            } else {
                $this->info('  All function tools synced successfully.');
            }
            $this->newLine();
        }

        if (!$functionOnly) {
            $this->line('→ Syncing MCP servers…');
            $mcpMetrics = $mcpToolSyncer->sync();
            $metrics = $metrics ? $metrics->mergeWith($mcpMetrics) : $mcpMetrics;
        }

        return $metrics?->writeToCli($this->output) ?? self::SUCCESS;
    }
}
