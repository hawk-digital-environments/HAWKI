<?php

namespace App\Console\Commands\Tools;

use App\Models\Ai\Tools\AiTool;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Console\Command;

class ListAllTools extends Command
{
    protected $signature = 'tools:list
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
        $registry       = app(ToolRegistry::class);
        $classBasedTools = config('tools.available_tools', []);

        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                    HAWKI TOOLS OVERVIEW                        ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // ── Class-based (function calling) tools ───────────────────────────
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->line('📦 <fg=yellow;options=bold>FUNCTION CALLING TOOLS</> (local execution)');
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        if (empty($classBasedTools)) {
            $this->warn('   No function-calling tools configured in config/tools.php');
        } else {
            // Index DB records by tool name for O(1) lookup
            $dbFunctionTools = AiTool::with('models')->where('type', 'function')->get()->keyBy('name');

            foreach ($classBasedTools as $toolClass) {
                try {
                    $tool   = app($toolClass);
                    $def    = $tool->getDefinition();
                    $dbTool = $dbFunctionTools->get($def->name);

                    $isInRegistry  = $registry->has($def->name) ? '<fg=green>✓ loaded</>' : '<fg=yellow>⚠ not in registry</>';
                    $dbStatus      = $dbTool
                        ? '<fg=green>✓ registered</>'
                        : '<fg=yellow>⚠ not in DB — run tools:add-function-tool</>';

                    $this->line("  <fg=green>●</> <fg=cyan>{$def->name}</> [{$isInRegistry}] [{$dbStatus}]");
                    $this->line("    <fg=gray>Class:</> {$toolClass}");
                    $this->line("    <fg=gray>Desc:</> {$def->description}");

                    if ($dbTool) {
                        $capability = $dbTool->capability ?: '<fg=gray>none</>';
                        $modelList  = $dbTool->models->isEmpty()
                            ? '<fg=red>no models assigned</>'
                            : $dbTool->models->map(fn($m) => $m->model_id)->join(', ');

                        $this->line("    <fg=gray>Capability:</> {$capability}");
                        $this->line("    <fg=gray>Models:</> {$modelList}");
                    }
                } catch (\Exception $e) {
                    $this->warn("  ERROR: {$toolClass}: {$e->getMessage()}");
                }
                $this->newLine();
            }
        }

        // ── DB-backed MCP tools ────────────────────────────────────────────
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->line('🌐 <fg=blue;options=bold>MCP TOOLS</> (DB-registered, remote execution)');
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        $dbTools = AiTool::with(['server', 'models'])->active()->mcp()->get();

        if ($dbTools->isEmpty()) {
            $this->warn('   No MCP tools in database. Use <comment>php artisan tools:add-mcp-server</comment>.');
        } else {
            $grouped = $dbTools->groupBy(fn($t) => $t->server?->server_label ?? 'unknown');
            foreach ($grouped as $serverLabel => $serverTools) {
                $server = $serverTools->first()->server;
                $this->line("  <fg=magenta>▶</> Server: <fg=magenta;options=bold>{$serverLabel}</> ({$server?->url})");
                $this->newLine();

                foreach ($serverTools as $tool) {
                    $modelList = $tool->models->isEmpty()
                        ? '<fg=red>no models assigned</>'
                        : $tool->models->map(fn($m) => $m->model_id)->join(', ');

                    $isInRegistry = $registry->has($tool->name) ? '<fg=green>✓ loaded</>' : '<fg=yellow>⚠ not in registry</>';

                    $this->line("    <fg=green>●</> <fg=cyan>{$tool->name}</> [{$isInRegistry}]");
                    $this->line("      <fg=gray>Desc:</> {$tool->description}");
                    $this->line("      <fg=gray>Models:</> {$modelList}");
                    $this->newLine();
                }
            }
        }

        // ── Summary ─────────────────────────────────────────────────────────
        $this->line('═══════════════════════════════════════════════════════════════');
        $registryTools = $registry->getAll();
        $this->line("Registry: <fg=green>" . count($registryTools) . "</> tools loaded");
        $this->line("💡 <fg=gray>Register function tools:</> <fg=cyan>tools:add-function-tool</>");
        $this->line("💡 <fg=gray>Register MCP tools:</> <fg=cyan>tools:add-mcp-server</>");
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
            'description' => $t->description,
            'server'      => $t->server?->server_label,
            'models'      => $t->models->pluck('model_id')->toArray(),
        ]);

        $this->line($tools->toJson(JSON_PRETTY_PRINT));
        return Command::SUCCESS;
    }
}
