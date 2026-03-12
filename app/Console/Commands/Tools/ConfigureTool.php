<?php

namespace App\Console\Commands\Tools;

use App\Models\Ai\AiModel;
use App\Models\Ai\Tools\AiTool;
use Illuminate\Console\Command;

class ConfigureTool extends Command
{
    protected $signature = 'tools:configure
                            {--tool= : Tool name to configure (skips interactive selection)}';

    protected $description = 'Configure an AI tool\'s attributes (capability, description, active state)';

    public function handle(): int
    {
        $tool = $this->resolveTool();

        if (!$tool) {
            return Command::FAILURE;
        }

        $this->newLine();
        $this->showToolSummary($tool);
        $this->newLine();

        $changed = false;

        // ── Active toggle ──────────────────────────────────────────────────────
        $currentActive = $tool->active ? 'enabled' : 'disabled';
        if ($this->confirm("Toggle active state? (currently <fg=cyan>{$currentActive}</>)", false)) {
            $tool->active = !$tool->active;
            $changed = true;
            $newState = $tool->active ? 'enabled' : 'disabled';
            $this->info("  ✓ Active state → <fg=cyan>{$newState}</>");
        }

        // ── Capability ────────────────────────────────────────────────────────
        $this->line("  Current capability: <fg=yellow>{$tool->capability}</>");
        if ($this->confirm("Update capability?", false)) {
            $existing = AiTool::whereNotNull('capability')
                ->where('capability', '!=', '')
                ->where('id', '!=', $tool->id)
                ->distinct()
                ->pluck('capability')
                ->toArray();

            $newOption = '[+ Enter a new capability]';
            $choices   = array_merge([$newOption], $existing);
            $choice    = $this->choice('Select or enter a capability', $choices, 0);

            if ($choice === $newOption) {
                $default = $tool->capability;
                $choice  = $this->ask('Enter capability name (snake_case, e.g. knowledge_base)', $default);
                $choice  = trim($choice) ?: $default;
            }

            $tool->capability = $choice;
            $changed = true;
            $this->info("  ✓ Capability → <fg=cyan>{$choice}</>");
        }

        // ── Description ───────────────────────────────────────────────────────
        $this->line("  Current description: <fg=gray>{$tool->description}</>");
        if ($this->confirm("Update description?", false)) {
            $desc = $this->ask('Enter new description', $tool->description);
            if ($desc !== null && $desc !== $tool->description) {
                $tool->description = $desc;
                $changed = true;
                $this->info("  ✓ Description updated.");
            }
        }

        if (!$changed) {
            $this->line('  No changes made.');
            return Command::SUCCESS;
        }

        $tool->save();
        AiModel::clearCapabilitiesCache();

        $this->newLine();
        $this->info('✓ Tool updated successfully.');
        $this->line('  Restart the application (or reload) for registry changes to take effect.');

        return Command::SUCCESS;
    }

    private function resolveTool(): ?AiTool
    {
        $toolName = $this->option('tool');

        if ($toolName) {
            $tool = AiTool::where('name', $toolName)->first();
            if (!$tool) {
                $this->error("Tool '{$toolName}' not found.");
                return null;
            }
            return $tool;
        }

        $tools = AiTool::orderBy('type')->orderBy('name')->get();

        if ($tools->isEmpty()) {
            $this->warn('No tools registered. Run <comment>php artisan tools:sync</comment> first.');
            return null;
        }

        $labels = $tools->map(fn($t) => sprintf(
            '[%s] %s (%s)',
            $t->active ? 'on' : 'off',
            $t->name,
            $t->type
        ))->toArray();

        $chosen = $this->choice('Select a tool to configure', $labels);
        $index  = array_search($chosen, $labels);

        return $tools[$index] ?? null;
    }

    private function showToolSummary(AiTool $tool): void
    {
        $activeLabel = $tool->active ? '<fg=green>enabled</>' : '<fg=red>disabled</>';
        $statusLabel = $tool->status === 'active' ? '<fg=green>online</>' : '<fg=yellow>offline</>';

        $this->line('  <fg=cyan;options=bold>' . $tool->name . '</>');
        $this->line("  Type:        {$tool->type}");
        $this->line("  Active:      {$activeLabel}");
        $this->line("  Status:      {$statusLabel}");
        $this->line("  Capability:  <fg=yellow>{$tool->capability}</>");
        $this->line("  Description: {$tool->description}");

        if ($tool->class_name) {
            $exists = class_exists($tool->class_name) ? '<fg=green>✓ exists</>' : '<fg=red>✗ missing</>';
            $this->line("  Class:       {$tool->class_name} [{$exists}]");
        }
    }
}
