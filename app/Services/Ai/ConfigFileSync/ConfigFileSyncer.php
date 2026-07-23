<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync;

use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Attributes\Tag;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates all registered {@see ConfigSyncerInterface} implementations in a single
 * sync pass and tracks the outcome via {@see JobMetrics}.
 *
 * Called by the `ai:config:sync` Artisan command and during database migrations via
 * {@see \App\Services\Ai\ConfigFileSync\ConfigSyncMigrationTrait}. Syncers are discovered
 * automatically through the Laravel service-container tag `ConfigSyncerInterface::class`
 * (registered in {@see \App\Providers\AiServiceProvider}).
 *
 * Change detection is delegated to {@see SyncActionDetector}: a SHA-256 hash of every
 * syncer's current config state is compared with the previously cached hash. Unchanged
 * configs are skipped unless `$force = true` is passed.
 *
 * @internal
 */
#[Singleton]
readonly class ConfigFileSyncer
{
    public function __construct(
        /**
         * @var iterable<ConfigSyncerInterface> $syncers
         */
        #[Tag(ConfigSyncerInterface::class)]
        private iterable           $syncers,
        private SyncActionDetector $detector,
        private LoggerInterface    $logger
    )
    {
    }

    /**
     * Runs all registered syncers and returns the collected metrics.
     *
     * Returns null without doing any work when the config hash is unchanged since the
     * last successful sync, unless `$force` is true. Syncer failures are caught
     * individually and recorded as errors in the metrics rather than aborting the
     * remaining syncers.
     *
     * @param bool $force Skip change-detection and always perform a full sync.
     * @return JobMetrics|null Null when nothing was synced because the config was already up to date.
     */
    public function sync(bool $force = false): ?JobMetrics
    {
        if (!$force && !$this->detector->shouldSync($this->syncers)) {
            return null;
        }

        $metrics = new JobMetrics('AI Config File Sync', $this->logger);

        foreach ($this->syncers as $syncer) {
            try {
                $syncer->sync($metrics);
            } catch (\Throwable $e) {
                $metrics->error(sprintf(
                    'Error syncing config with %s: %s',
                    get_class($syncer),
                    $e->getMessage()
                ), ['exception' => $e]);
            }
        }

        $this->detector->markAsSynced($this->syncers);

        return $metrics;
    }

}
