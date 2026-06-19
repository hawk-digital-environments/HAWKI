<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync;

use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Attributes\Tag;
use Psr\Log\LoggerInterface;

/**
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
