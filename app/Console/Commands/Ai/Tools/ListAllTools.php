<?php

namespace App\Console\Commands\Ai\Tools;

use App\Models\Ai\AiTool;
use App\Services\Ai\Values\OnlineStatus;
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
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                    HAWKI TOOLS OVERVIEW                        ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $allTools = AiTool::with(['server', 'models'])->get();

        if ($allTools->isEmpty()) {
            $this->warn('No tools in database. Run <comment>php artisan ai:tools:sync</comment> to populate.');
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
            $this->warn('   No function-calling tools in database. Run <comment>php artisan ai:tools:sync --function-only</comment>.');
        } else {
            /** @var AiTool|null $tool */
            foreach ($functionTools as $tool) {
                $modelCount = $tool->models->count();
                $modelCountLabel = $modelCount > 0
                    ? "<fg=green>{$modelCount} model(s)</>"
                    : '<fg=red>no models assigned</>';
                $classOk = $tool->class_name && class_exists($tool->class_name)
                    ? '<fg=green>✓ exists</>'
                    : '<fg=red>✗ class missing</>';
                $activeLabel = $tool->active ? '<fg=green>enabled</>' : '<fg=red>disabled</>';

                $modelList = $tool->models->isEmpty()
                    ? '<fg=red>no models assigned</>'
                    : $tool->models->map(fn($m) => $m->model_id)->join(', ');

                $this->line("  <fg=green>●</> <fg=cyan>{$tool->name}</> [{$activeLabel}] [{$modelCountLabel}]");
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
            /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, AiTool>> $grouped */
            $grouped = $mcpTools->groupBy(fn($t) => $t->server->server_label ?? 'unknown');
            foreach ($grouped as $serverLabel => $serverTools) {
                $server = $serverTools->first()->server;
                $this->line("  <fg=magenta>▶</> Server: <fg=magenta;options=bold>{$serverLabel}</> ({$server?->url})");
                $this->newLine();

                foreach ($serverTools as $tool) {
                    $modelCount = $tool->models->count();
                    $modelCountLabel = $modelCount > 0
                        ? "<fg=green>{$modelCount} model(s)</>"
                        : '<fg=red>no models assigned</>';
                    $activeLabel = $tool->active ? '<fg=green>enabled</>' : '<fg=red>disabled</>';
                    $modelList = $tool->models->isEmpty()
                        ? '<fg=red>no models assigned</>'
                        : $tool->models->map(fn($m) => $m->model_id)->join(', ');

                    match ($tool->server->status) {
                        OnlineStatus::ONLINE => $serverStatus = '<fg=green>online</>',
                        OnlineStatus::OFFLINE => $serverStatus = '<fg=red>offline</>',
                        OnlineStatus::UNKNOWN => $serverStatus = '<fg=gray>unknown</>',
                    };

                    $this->line("    $serverStatus <fg=cyan>{$tool->name}</> [{$activeLabel}] [{$modelCountLabel}]");
                    $this->line("      <fg=gray>Desc:</> {$tool->description}");
                    $this->line("      <fg=gray>Models:</> {$modelList}");
                    $this->newLine();
                }
            }
        }

        // ── Summary ────────────────────────────────────────────────────────────
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->line("💡 <fg=gray>Sync tools from config:</> <fg=cyan>ai:tools:sync</>");
        $this->line("💡 <fg=gray>Add MCP server:</> <fg=cyan>tools:add-mcp-server</>");
        $this->line("💡 <fg=gray>Manage model assignments:</> <fg=cyan>tools:assign</>");
        $this->newLine();

        return Command::SUCCESS;
    }

    private function outputJson(): int
    {
        $tools = AiTool::with(['server', 'models'])->get()->map(fn($t) => [
            'name' => $t->name,
            'type' => $t->type->value,
            'active' => $t->active,
            'class_name' => $t->class_name,
            'description' => $t->description,
            'server' => $t->server?->server_label,
            'models' => $t->models->pluck('model_id')->toArray(),
        ]);

        $this->line($tools->toJson(JSON_PRETTY_PRINT));
        return Command::SUCCESS;
    }
}
