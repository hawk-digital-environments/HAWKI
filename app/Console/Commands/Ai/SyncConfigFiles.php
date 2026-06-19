<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\ConfigFileSync\ConfigFileSyncer;
use Illuminate\Console\Command;

/**
 * @internal
 */
class SyncConfigFiles extends Command
{
    protected $signature = 'ai:config:sync
                            {--force : Enforce a sync, even if already in synced state}';

    protected $aliases = [
        'ai:models:sync' // For legacy reasons
    ];

    protected $description = 'Sync AI config files into the database';

    public function handle(ConfigFileSyncer $syncHandler): int
    {
        $this->info('Syncing AI models from config into database…');

        $force = $this->option('force');

        $metrics = $syncHandler->sync($force);
        if ($metrics === null) {
            $this->info('Models are already synced. Use --force to re-sync.');
            return self::SUCCESS;
        }

        $metrics->writeToCli($this->output);

        return $metrics->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
