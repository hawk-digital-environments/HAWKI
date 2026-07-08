<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\ConfigFileSync\ConfigFileSyncer;
use App\Services\System\Container\ServiceLocator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

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

    public function handle(ServiceLocator $serviceLocator, Filesystem $files): int
    {
        $this->info('Syncing static file config into database…');

        // Force load the config files to ensure we have the latest state before syncing
        $files->delete($this->laravel->getCachedConfigPath());
        (new LoadConfiguration())->bootstrap($this->laravel);

        // Now, lazy-load the ConfigFileSyncer service to perform the sync operation
        $syncHandler = $serviceLocator->get(ConfigFileSyncer::class);

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
