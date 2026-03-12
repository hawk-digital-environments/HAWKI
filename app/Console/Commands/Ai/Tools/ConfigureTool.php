<?php

namespace App\Console\Commands\Ai\Tools;

use App\Models\Ai\AiModel;
use App\Models\Ai\Tools\AiTool;
use Illuminate\Console\Command;

class ConfigureTool extends Command
{
    protected $signature = 'ai:tools:configure
                            {--tool= : Tool name to configure (skips interactive selection)}';

    protected $description = 'Configure an AI tool\'s attributes (capability, description, active state)';

    public function handle(): int
    {
        $tool = $this->resolveTool();

        if (!$tool) {
            return Command::FAILURE;
        }

        $changed = false;

        while (true) {
            $this->newLine();
            $this->showToolSummary($tool);
            $this->newLine();

            $activeLabel = $tool->active ? 'enabled' : 'disabled';
            $choices = [
                "0  Active:      {$activeLabel}",
                "1  Capability:  {$tool->capability}",
                "2  Description: {$tool->description}",
                '3  Done',
            ];

            $selected = $this->choice('Select a field to edit', $choices, '3  Done');

            if ($selected === '3  Done') {
                break;
            }

            $field = (int) $selected[0];

            match ($field) {
                0 => $changed = $this->editActive($tool) || $changed,
                1 => $changed = $this->editCapability($tool) || $changed,
                2 => $changed = $this->editDescription($tool) || $changed,
            };
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

    private function editActive(AiTool $tool): bool
    {
        $current = $tool->active ? 'enabled' : 'disabled';
        $new     = $tool->active ? 'disabled' : 'enabled';
        if ($this->confirm("Toggle active state from <fg=cyan>{$current}</> to <fg=cyan>{$new}</>?", true)) {
            $tool->active = !$tool->active;
            $this->info("  ✓ Active → <fg=cyan>{$new}</>");
            return true;
        }
        return false;
    }

    private function editCapability(AiTool $tool): bool
    {
        $existing = AiTool::whereNotNull('capability')
            ->where('capability', '!=', '')
            ->where('id', '!=', $tool->id)
            ->distinct()
            ->pluck('capability')
            ->toArray();

        $newOption = '[+ Enter a new capability]';
        $choices   = array_merge([$newOption], $existing);
        $choice    = $this->choice(
            "Select or enter a capability  <fg=gray>(current: {$tool->capability}</>)",
            $choices,
            0
        );

        if ($choice === $newOption) {
            $choice = trim($this->ask('Enter capability name (snake_case, e.g. knowledge_base)', $tool->capability))
                ?: $tool->capability;
        }

        if ($choice === $tool->capability) {
            $this->line('  No change.');
            return false;
        }

        $tool->capability = $choice;
        $this->info("  ✓ Capability → <fg=cyan>{$choice}</>");
        return true;
    }

    private function editDescription(AiTool $tool): bool
    {
        $desc = $this->ask('Enter new description', $tool->description);

        if ($desc === null || $desc === $tool->description) {
            $this->line('  No change.');
            return false;
        }

        $tool->description = $desc;
        $this->info('  ✓ Description updated.');
        return true;
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
            $this->warn('No tools registered. Run <comment>php artisan ai:tools:sync</comment> first.');
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
        $this->line("  Status:      {$statusLabel}");
        $this->line("  Active:      {$activeLabel}");
        $this->line("  Capability:  <fg=yellow>{$tool->capability}</>");
        $this->line("  Description: {$tool->description}");

        if ($tool->class_name) {
            $exists = class_exists($tool->class_name) ? '<fg=green>✓ exists</>' : '<fg=red>✗ missing</>';
            $this->line("  Class:       {$tool->class_name} [{$exists}]");
        }
    }
}
