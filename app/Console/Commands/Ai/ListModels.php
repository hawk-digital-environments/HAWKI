<?php

namespace App\Console\Commands\Ai;

use App\Models\Ai\AiModel;
use Illuminate\Console\Command;

class ListModels extends Command
{
    protected $signature = 'ai:models:list
                            {--provider= : Filter by provider_id (e.g. openAi, gwdg)}
                            {--active    : Show only active models}
                            {--json      : Output as JSON}';

    protected $description = 'List all AI models registered in the database';

    public function handle(): int
    {
        $query = AiModel::with('provider', 'status');

        if ($provider = $this->option('provider')) {
            $query->whereHas('provider', fn($q) => $q->where('provider_id', $provider));
        }

        if ($this->option('active')) {
            $query->where('active', true);
        }

        $models = $query->orderBy('provider_id')->orderBy('model_id')->get();

        if ($models->isEmpty()) {
            $this->warn('No models found. Run <comment>php artisan ai:models:sync</comment> first.');
            return Command::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($models->toJson(JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        // Group by provider
        $grouped = $models->groupBy(fn($m) => $m->provider?->provider_id ?? 'unknown');

        foreach ($grouped as $providerId => $providerModels) {
            $provider = $providerModels->first()->provider;
            $status   = $provider?->active ? '<fg=green>active</>' : '<fg=red>inactive</>';
            $this->newLine();
            $this->line("Provider: <fg=cyan;options=bold>{$providerId}</> [{$status}]");
            $this->line(str_repeat('─', 70));

            $rows = $providerModels->map(fn($m) => [
                $m->model_id,
                $m->label,
                $m->active ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $m->status?->status?->value ?? 'unknown',
                implode(', ', $m->input ?? []),
            ])->toArray();

            $this->table(['Model ID', 'Label', 'Active', 'Status', 'Input'], $rows);
        }

        $this->newLine();
        $this->line("Total: <fg=green>{$models->count()}</> models");

        return Command::SUCCESS;
    }
}
