<?php

namespace App\Console\Commands\Models;

use App\Services\AI\Db\AiModelSyncService;
use Illuminate\Console\Command;

class SyncAiModels extends Command
{
    protected $signature = 'models:sync
                            {--force : Re-sync even if models already exist in DB}';

    protected $description = 'Sync AI models and providers from config files into the database';

    public function handle(AiModelSyncService $syncService): int
    {
        if (!$this->option('force') && $syncService->isSynced()) {
            $this->info('Models are already synced. Use --force to re-sync.');
            return Command::SUCCESS;
        }

        $this->info('Syncing AI models from config into database…');

        $stats = $syncService->sync();

        $this->info("  ✓ Providers synced: {$stats['providers_synced']}");
        $this->info("  ✓ Models synced:    {$stats['models_synced']}");
        $this->newLine();
        $this->info('Done. Run <comment>php artisan models:list</comment> to review the results.');

        return Command::SUCCESS;
    }
}
