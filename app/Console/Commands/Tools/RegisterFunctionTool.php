<?php

namespace App\Console\Commands\Tools;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\Tools\AiTool;
use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Console\Command;

class RegisterFunctionTool extends Command
{
    protected $signature = 'tools:add-function-tool';

    protected $description = 'Register an in-project function-call tool into the DB so it can be assigned to models and appear in the frontend';

    public function handle(): int
    {
        $registry = app(ToolRegistry::class);

        // Only non-MCP tools are managed here; MCP tools go through tools:add-mcp-server
        $functionTools = array_filter(
            $registry->getAll(),
            fn($t) => !($t instanceof MCPToolInterface)
        );

        if (empty($functionTools)) {
            $this->warn('No function-call tools found in the registry.');
            $this->line('Add tool classes to <comment>config/tools.php → available_tools</comment> and restart.');
            return Command::FAILURE;
        }

        // Let user pick which tools to register/update
        $toolNames = array_keys($functionTools);
        $selected  = $this->choice(
            'Select tools to register (comma-separate for multiple)',
            $toolNames,
            null, null, true
        );

        if (!is_array($selected)) {
            $selected = [$selected];
        }

        if (empty($selected)) {
            $this->info('No tools selected.');
            return Command::SUCCESS;
        }

        // Collect existing capability names for reuse suggestions
        $existingCapabilities = AiTool::whereNotNull('capability')
            ->where('capability', '!=', '')
            ->pluck('capability')
            ->unique()
            ->values()
            ->toArray();

        $registeredTools = [];

        foreach ($selected as $toolName) {
            $tool       = $functionTools[$toolName];
            $definition = $tool->getDefinition();

            $this->newLine();
            $this->line("<fg=cyan;options=bold>Tool: {$toolName}</>");
            $this->line("  Description: {$definition->description}");

            $capability = $this->askCapability($toolName, $existingCapabilities);

            if (!in_array($capability, $existingCapabilities, true)) {
                $existingCapabilities[] = $capability;
            }
            \Log::debug($toolName);
            $aiTool = AiTool::updateOrCreate(
                ['name' => $toolName],
                [
                    'description' => $definition->description,
                    'inputSchema' => $definition->parameters,
                    'capability'  => $capability,
                    'type'        => 'function',
                    'status'      => 'active',
                    'server_id'   => null,
                ]
            );

            $registeredTools[] = $aiTool;
            $this->info("  ✓ Registered <fg=cyan>{$toolName}</> with capability <fg=green>{$capability}</>");
        }

        // Offer to assign to models immediately
        $this->newLine();
        if (!$this->confirm('Assign these tools to AI models now?', true)) {
            AiModel::clearCapabilitiesCache();
            $this->line('Done. Use <comment>php artisan tools:assign</comment> later to assign to models.');
            return Command::SUCCESS;
        }

        $models = $this->selectModels();

        if (!empty($models)) {
            $this->assignToModels($registeredTools, $models);
        }

        AiModel::clearCapabilitiesCache();
        return Command::SUCCESS;
    }

    // ── Capability selection ───────────────────────────────────────────────────

    private function askCapability(string $toolName, array $existingCapabilities): string
    {
        $newOption = '[+ Define a new capability]';
        $choices   = array_merge([$newOption], $existingCapabilities);
        $choice    = $this->choice('  Select or create a capability', $choices, 0);

        if ($choice === $newOption) {
            $default    = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $toolName));
            $capability = $this->ask('  Enter capability name (snake_case, e.g. knowledge_base)', $default);
            return trim($capability) ?: $default;
        }

        return $choice;
    }

    // ── Model selection ────────────────────────────────────────────────────────

    private function selectModels(): array
    {
        $modeOptions = [
            'By Provider (assigns to all eligible models in the provider)',
            'By Model (select individual models)',
        ];

        $mode = $this->choice('How do you want to assign?', $modeOptions, 0);

        return $mode === $modeOptions[0]
            ? $this->selectByProvider()
            : $this->selectByModel();
    }

    private function selectByProvider(): array
    {
        $providers = AiProvider::withCount('models')->get();

        if ($providers->isEmpty()) {
            $this->warn('No providers found. Run <comment>php artisan models:sync</comment> first.');
            return [];
        }

        $labels   = $providers->map(fn($p) => "{$p->name} ({$p->models_count} models)")->toArray();
        $selected = $this->choice('Select providers (comma-separate multiple)', $labels, null, null, true);

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

        $this->line("  Found <fg=green>{$models->count()}</> eligible model(s).");
        return $models->all();
    }

    private function selectByModel(): array
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
        $chosen = $this->choice('Select models (comma-separate multiple)', $labels, null, null, true);

        if (!is_array($chosen)) {
            $chosen = [$chosen];
        }

        return $allModels->filter(
            fn($m) => in_array("{$m->model_id} ({$m->label})", $chosen)
        )->all();
    }

    // ── Assignment ─────────────────────────────────────────────────────────────

    private function assignToModels(array $tools, array $models): void
    {
        $count = 0;
        foreach ($tools as $tool) {
            foreach ($models as $model) {
                $model->assignedTools()->syncWithoutDetaching([
                    $tool->id => ['type' => $tool->type],
                ]);
                $count++;
            }
        }

        $this->newLine();
        $this->info("  ✓ Created {$count} model-tool assignment(s).");
        $this->line('  Use <comment>php artisan tools:assign --list</comment> to review assignments.');
    }
}
