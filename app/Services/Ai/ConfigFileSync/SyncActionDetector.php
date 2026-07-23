<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync;


use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * Determines whether the AI configuration has changed since it was last synced.
 *
 * Each {@see ConfigSyncerInterface} contributes a hash of its own config state.
 * Those hashes are combined into a single SHA-256 fingerprint and compared with
 * the value stored in the cache. When the fingerprints differ, a sync is needed.
 * After a successful sync the cache is updated to reflect the new state.
 *
 * The cache key is derived from the class name so it never collides with other
 * application cache entries.
 *
 * @internal
 */
class SyncActionDetector
{
    public function __construct(
        private readonly Repository $cache
    )
    {
    }

    /**
     * Determines if the AI configuration has changed since the last sync.
     * @param iterable<ConfigSyncerInterface> $syncers
     */
    public function shouldSync(iterable $syncers): bool
    {
        return $this->getCurrentConfigHash($syncers) !== $this->getCachedConfigHash();
    }

    /**
     * Marks the current AI configuration as synced by updating the cached hash.
     * @param iterable<ConfigSyncerInterface> $syncers
     */
    public function markAsSynced(iterable $syncers): void
    {
        $currentHash = $this->getCurrentConfigHash($syncers);
        $this->updateCachedConfigHash($currentHash);
    }

    /**
     * Clears the sync marker, forcing the next check to indicate that a sync is needed.
     */
    public function clearSyncMarker(): void
    {
        $this->cache->forget($this->getCacheKey());
    }

    /**
     * @param iterable<ConfigSyncerInterface> $syncers
     */
    private function getCurrentConfigHash(iterable $syncers): string
    {
        $data = [];
        foreach ($syncers as $syncer) {
            $data[get_class($syncer)] = $syncer->getCurrentHash();
        }
        return hash('sha256', json_encode($data));
    }

    private function getCachedConfigHash(): ?string
    {
        return $this->cache->get($this->getCacheKey());
    }

    private function updateCachedConfigHash(string $hash): void
    {
        $this->cache->forever($this->getCacheKey(), $hash);
    }

    private function getCacheKey(): string
    {
        return 'ai_config_hash.' . md5(__CLASS__);
    }
}
