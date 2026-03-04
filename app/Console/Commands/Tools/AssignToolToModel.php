<?php

namespace App\Console\Commands\Tools;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\Tools\AiTool;
use Illuminate\Console\Command;

class AssignToolToModel extends Command
{
    protected $signature = 'tools:assign
                            {--tool=     : Tool name (or partial match) to assign}
                            {--model=    : Model ID to assign the tool to}
                            {--provider= : Provider ID to assign the tool to all eligible models}
                            {--detach    : Remove the tool assignment instead of adding it}
                            {--list      : Show current model-tool assignments}';

    protected $description = 'Manage which AI models are allowed to use which tools';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->showAssignments();
        }

        if ($this->option('detach')) {
            return $this->detachTool();
        }

        return $this->attachTool();
    }

    // ── Attach / detach ────────────────────────────────────────────────────────

    private function attachTool(): int
    {
        [$tool, $models] = $this->resolveToolAndModels();

        if (!$tool || empty($models)) {
            return Command::FAILURE;
        }

        $count = 0;
        foreach ($models as $model) {
            $model->assignedTools()->syncWithoutDetaching([
                $tool->id => ['type' => $tool->type],
            ]);
            $count++;
        }

        AiModel::clearCapabilitiesCache();
        $this->info("  ✓ Tool <fg=cyan>{$tool->name}</> assigned to {$count} model(s).");
        return Command::SUCCESS;
    }

    private function detachTool(): int
    {
        [$tool, $models] = $this->resolveToolAndModels();

        if (!$tool || empty($models)) {
            return Command::FAILURE;
        }

        $count = 0;
        foreach ($models as $model) {
            $model->assignedTools()->detach($tool->id);
            $count++;
        }

        AiModel::clearCapabilitiesCache();
        $this->info("  ✓ Tool <fg=cyan>{$tool->name}</> detached from {$count} model(s).");
        return Command::SUCCESS;
    }

    private function showAssignments(): int
    {
        $tools = AiTool::with('models.provider')->get();

        if ($tools->isEmpty()) {
            $this->warn('No tools registered in the database.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Tool → Model Assignments</>');
        $this->line(str_repeat('─', 70));

        foreach ($tools as $tool) {
            $modelList = $tool->models->isEmpty()
                ? '<fg=red>none</>'
                : $tool->models->map(fn($m) => $m->model_id)->join(', ');

            $this->line(sprintf(
                "  <fg=yellow>%s</> [%s]  →  %s",
                $tool->name,
                $tool->type,
                $modelList
            ));
        }

        $this->newLine();
        return Command::SUCCESS;
    }

    // ── Resolution helpers ─────────────────────────────────────────────────────

    /**
     * @return array{AiTool|null, AiModel[]}
     */
    private function resolveToolAndModels(): array
    {
        // ── Resolve tool ───────────────────────────────────────────────────────
        $toolSearch = $this->option('tool');
        $tool       = null;

        if ($toolSearch) {
            $tool = AiTool::where('name', $toolSearch)
                ->orWhere('name', 'like', "%{$toolSearch}%")
                ->first();
        }

        if (!$tool) {
            $tools = AiTool::all();
            if ($tools->isEmpty()) {
                $this->warn('No tools registered. Use <comment>php artisan tools:add-mcp-server</comment> first.');
                return [null, []];
            }
            $choice = $this->choice('Select a tool', $tools->pluck('name')->toArray());
            $tool   = $tools->firstWhere('name', $choice);
        }

        if (!$tool) {
            $this->error('Tool not found.');
            return [null, []];
        }

        // ── Resolve models ─────────────────────────────────────────────────────
        $modelSearch    = $this->option('model');
        $providerSearch = $this->option('provider');
        $models         = [];

        if ($modelSearch) {
            // Non-interactive: find by model_id
            $found = AiModel::where('model_id', $modelSearch)->get();
            if ($found->isEmpty()) {
                $this->error("Model '{$modelSearch}' not found.");
                return [$tool, []];
            }
            $models = $found->all();

        } elseif ($providerSearch) {
            // Non-interactive: find by provider_id or name
            $provider = AiProvider::where('provider_id', $providerSearch)
                ->orWhere('name', 'like', "%{$providerSearch}%")
                ->first();

            if (!$provider) {
                $this->error("Provider '{$providerSearch}' not found.");
                return [$tool, []];
            }

            $eligible = AiModel::where('provider_id', $provider->id)
                ->where('active', true)
                ->get()
                ->filter(fn($m) => ($m->tools ?: [])['tool_calling'] ?? true);

            if ($eligible->isEmpty()) {
                $this->warn("No eligible models found for provider '{$provider->name}'.");
                $this->line('Note: Models with <fg=yellow>tool_calling: false</> are excluded.');
                return [$tool, []];
            }

            $this->line("  Found <fg=green>{$eligible->count()}</> eligible model(s) in '{$provider->name}'.");
            $models = $eligible->all();

        } else {
            // Interactive: ask by provider or by model
            $modeOptions = [
                'By Provider (assigns to all eligible models in the provider)',
                'By Model (select individual models)',
            ];

            $mode = $this->choice('How do you want to assign?', $modeOptions, 0);

            if ($mode === $modeOptions[0]) {
                $models = $this->selectByProvider($tool);
            } else {
                $models = $this->selectByModel($tool);
            }
        }

        return [$tool, $models];
    }

    /**
     * Interactive provider selection — returns array of eligible AiModel instances.
     */
    private function selectByProvider(AiTool $tool): array
    {
        $providers = AiProvider::withCount('models')->get();

        if ($providers->isEmpty()) {
            $this->warn('No providers found. Run <comment>php artisan models:sync</comment> first.');
            return [];
        }

        $providerLabels = $providers->map(fn($p) => "{$p->name} ({$p->models_count} models)")->toArray();

        $selected = $this->choice(
            "Select providers for tool '{$tool->name}' (comma-separate multiple)",
            $providerLabels,
            null, null, true
        );

        if (empty($selected)) {
            $this->info('No providers selected.');
            return [];
        }

        if (!is_array($selected)) {
            $selected = [$selected];
        }

        $selectedNames = collect($selected)
            ->map(fn($s) => preg_replace('/\s*\(\d+ models\)$/', '', $s))
            ->toArray();

        $models = AiModel::whereHas('provider', fn($q) => $q->whereIn('name', $selectedNames))
            ->where('active', true)
            ->get()
            ->filter(fn($m) => ($m->tools ?: [])['tool_calling'] ?? true);

        if ($models->isEmpty()) {
            $this->warn('No eligible models found for the selected providers.');
            $this->line('Note: Models with <fg=yellow>tool_calling: false</> are excluded.');
            return [];
        }

        $this->line("  Found <fg=green>{$models->count()}</> eligible model(s) across selected provider(s).");
        return $models->all();
    }

    /**
     * Interactive model selection — returns array of selected AiModel instances.
     */
    private function selectByModel(AiTool $tool): array
    {
        $allModels = AiModel::with('provider')
            ->where('active', true)
            ->orderBy('model_id')
            ->get()
            ->filter(fn($m) => ($m->tools ?: [])['tool_calling'] ?? true);

        if ($allModels->isEmpty()) {
            $this->warn('No active tool-capable models found. Run <comment>php artisan models:sync</comment> first.');
            return [];
        }

        $labels = $allModels->map(fn($m) => "{$m->model_id} ({$m->label})")->values()->toArray();

        $chosen = $this->choice(
            "Select models for tool '{$tool->name}' (comma-separate multiple)",
            $labels,
            null, null, true
        );

        if (!is_array($chosen)) {
            $chosen = [$chosen];
        }

        return $allModels->filter(
            fn($m) => in_array("{$m->model_id} ({$m->label})", $chosen)
        )->all();
    }
}
