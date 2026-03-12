<?php

namespace App\Console\Commands\Ai\Tools;

use App\Models\Ai\Tools\AiTool;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Console\Command;

class ListAllTools extends Command
{
    protected $signature = 'ai:tools:list
                            {--json : Output as JSON}';

    protected $description = 'List all available tools (function-call and MCP)';

    public function handle(): int
    {
        if ($this->option('json')) {
            return $this->outputJson();
        }

        return $this->outputFormatted();
    }

    private function outputFormatted(): int
    {
        $registry = app(ToolRegistry::class);

        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                    HAWKI TOOLS OVERVIEW                        ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $allTools = AiTool::with(['server', 'models'])->get();

        if ($allTools->isEmpty()) {
            $this->warn('No tools in database. Run <comment>php artisan tools:sync</comment> to populate.');
            $this->newLine();
            return Command::SUCCESS;
        }

        // ── Function tools ─────────────────────────────────────────────────────
        $functionTools = $allTools->where('type', 'function');

        $this->line('═══════════════════════════════════════════════════════════════');
        $this->line('📦 <fg=yellow;options=bold>FUNCTION CALLING TOOLS</> (local execution)');
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        if ($functionTools->isEmpty()) {
            $this->warn('   No function-calling tools in database. Run <comment>php artisan tools:sync --function-only</comment>.');
        } else {
            foreach ($functionTools as $tool) {
                $isInRegistry = $registry->has($tool->name) ? '<fg=green>✓ loaded</>' : '<fg=yellow>⚠ not in registry</>';
                $classOk      = $tool->class_name && class_exists($tool->class_name)
                    ? '<fg=green>✓ exists</>'
                    : '<fg=red>✗ class missing</>';
                $activeLabel  = $tool->active ? '<fg=green>enabled</>' : '<fg=red>disabled</>';
                $statusLabel  = $tool->status === 'active' ? '<fg=green>online</>' : '<fg=yellow>offline</>';

                $modelList = $tool->models->isEmpty()
                    ? '<fg=red>no models assigned</>'
                    : $tool->models->map(fn($m) => $m->model_id)->join(', ');

                $this->line("  <fg=green>●</> <fg=cyan>{$tool->name}</> [{$activeLabel}] [{$statusLabel}] [{$isInRegistry}]");
                $this->line("    <fg=gray>Class:</> {$tool->class_name} [{$classOk}]");
                $this->line("    <fg=gray>Desc:</> {$tool->description}");
                $this->line("    <fg=gray>Capability:</> " . ($tool->capability ?: '<fg=gray>none</>'));
                $this->line("    <fg=gray>Models:</> {$modelList}");
                $this->newLine();
            }
        }

        // ── MCP tools ──────────────────────────────────────────────────────────
        $mcpTools = $allTools->where('type', 'mcp');

        $this->line('═══════════════════════════════════════════════════════════════');
        $this->line('🌐 <fg=blue;options=bold>MCP TOOLS</> (DB-registered, remote execution)');
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        if ($mcpTools->isEmpty()) {
            $this->warn('   No MCP tools in database. Use <comment>php artisan tools:add-mcp-server</comment>.');
        } else {
            $grouped = $mcpTools->groupBy(fn($t) => $t->server?->server_label ?? 'unknown');
            foreach ($grouped as $serverLabel => $serverTools) {
                $server = $serverTools->first()->server;
                $this->line("  <fg=magenta>▶</> Server: <fg=magenta;options=bold>{$serverLabel}</> ({$server?->url})");
                $this->newLine();

                foreach ($serverTools as $tool) {
                    $isInRegistry = $registry->has($tool->name) ? '<fg=green>✓ loaded</>' : '<fg=yellow>⚠ not in registry</>';
                    $activeLabel  = $tool->active ? '<fg=green>enabled</>' : '<fg=red>disabled</>';
                    $statusLabel  = $tool->status === 'active' ? '<fg=green>online</>' : '<fg=yellow>offline</>';
                    $modelList    = $tool->models->isEmpty()
                        ? '<fg=red>no models assigned</>'
                        : $tool->models->map(fn($m) => $m->model_id)->join(', ');

                    $this->line("    <fg=green>●</> <fg=cyan>{$tool->name}</> [{$activeLabel}] [{$statusLabel}] [{$isInRegistry}]");
                    $this->line("      <fg=gray>Desc:</> {$tool->description}");
                    $this->line("      <fg=gray>Models:</> {$modelList}");
                    $this->newLine();
                }
            }
        }

        // ── Summary ────────────────────────────────────────────────────────────
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->line("Registry: <fg=green>" . count($registry->getAll()) . "</> tools loaded");
        $this->line("💡 <fg=gray>Sync tools from config:</> <fg=cyan>tools:sync</>");
        $this->line("💡 <fg=gray>Add MCP server:</> <fg=cyan>tools:add-mcp-server</>");
        $this->line("💡 <fg=gray>Manage model assignments:</> <fg=cyan>tools:assign</>");
        $this->newLine();

        return Command::SUCCESS;
    }

    private function outputJson(): int
    {
        $tools = AiTool::with(['server', 'models'])->get()->map(fn($t) => [
            'name'        => $t->name,
            'type'        => $t->type,
            'status'      => $t->status,
            'class_name'  => $t->class_name,
            'description' => $t->description,
            'server'      => $t->server?->server_label,
            'models'      => $t->models->pluck('model_id')->toArray(),
        ]);

        $this->line($tools->toJson(JSON_PRETTY_PRINT));
        return Command::SUCCESS;
    }
}
