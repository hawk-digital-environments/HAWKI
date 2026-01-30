<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateUsageRecordsApiProvider extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage-records:populate-api-provider
                            {--dry-run : Show what would be updated without actually doing it}
                            {--force : Force update even if records are already processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate api_provider field in existing usage_records by looking up provider through model relationship';

    protected int $updatedCount = 0;
    protected int $skippedCount = 0;
    protected int $notFoundCount = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Starting api_provider population for usage_records...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No records will be actually updated');
        }

        // Get all usage records with NULL api_provider (or all if force is enabled)
        $query = DB::table('usage_records');
        
        if (!$force) {
            $query->whereNull('api_provider');
        }
        
        $records = $query->get(['id', 'model', 'api_provider']);

        if ($records->isEmpty()) {
            $this->info('No usage records found to process.');
            return 0;
        }

        $this->info("Found {$records->count()} usage records to process.");

        $progressBar = $this->output->createProgressBar($records->count());
        $progressBar->setFormat('Processing: %current%/%max% [%bar%] %percent:3s%%');
        $progressBar->start();

        foreach ($records as $record) {
            $this->processRecord($record, $isDryRun, $force);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Display summary
        $this->displaySummary($isDryRun);

        return 0;
    }

    protected function processRecord($record, bool $isDryRun, bool $force): void
    {
        try {
            // Skip if already has api_provider and not forcing
            if (!$force && $record->api_provider !== null) {
                if ($this->output->isVerbose()) {
                    $this->line("Skipping record {$record->id} - already has api_provider: {$record->api_provider}");
                }
                $this->skippedCount++;
                return;
            }

            // Find the AI model by model_id
            $aiModel = DB::table('ai_models')
                ->where('model_id', $record->model)
                ->first(['provider_id']);

            if (!$aiModel || !$aiModel->provider_id) {
                if ($this->output->isVerbose()) {
                    $this->warn("Could not find ai_model for usage_record {$record->id} (model: {$record->model})");
                }
                $this->notFoundCount++;
                return;
            }

            // Get the provider's unique_name
            $provider = DB::table('api_providers')
                ->where('id', $aiModel->provider_id)
                ->first(['unique_name']);

            if (!$provider || !$provider->unique_name) {
                if ($this->output->isVerbose()) {
                    $this->warn("Could not find api_provider for usage_record {$record->id} (provider_id: {$aiModel->provider_id})");
                }
                $this->notFoundCount++;
                return;
            }

            if ($isDryRun) {
                $this->line("Would update record {$record->id}: model='{$record->model}' -> api_provider='{$provider->unique_name}'");
                $this->updatedCount++;
                return;
            }

            // Update the usage record with the provider's unique_name
            DB::table('usage_records')
                ->where('id', $record->id)
                ->update(['api_provider' => $provider->unique_name]);

            if ($this->output->isVerbose()) {
                $this->line("✓ Updated record {$record->id}: api_provider='{$provider->unique_name}'");
            }
            $this->updatedCount++;

        } catch (\Throwable $e) {
            $this->error("Error processing record {$record->id}: " . $e->getMessage());
            $this->notFoundCount++;
        }
    }

    protected function displaySummary(bool $isDryRun): void
    {
        $action = $isDryRun ? 'Would be updated' : 'Updated';

        $this->info('Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                [$action, $this->updatedCount],
                ['Skipped', $this->skippedCount],
                ['Not Found', $this->notFoundCount],
                ['Total', $this->updatedCount + $this->skippedCount + $this->notFoundCount]
            ]
        );

        if ($this->notFoundCount > 0) {
            $this->warn("There were {$this->notFoundCount} records where the provider could not be determined.");
            $this->comment('Run with -v or -vv for more details.');
        }

        if (!$isDryRun && $this->updatedCount > 0) {
            $this->info("Successfully updated {$this->updatedCount} usage records with api_provider information.");
        }

        if ($isDryRun) {
            $this->info('Run without --dry-run to perform the actual update.');
        }
    }
}
