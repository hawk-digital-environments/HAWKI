<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers\Contract;

use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Implementing this interface indicates that the handler supports full synchronization operations.
 * If NOT present on a handler, only delta sync operations are supported e.g. for transient resources.
 *
 * @template T
 * @extends SyncLogHandlerInterface<T>
 */
interface FullSyncLogHandlerInterface extends SyncLogHandlerInterface
{
    /**
     * MUST return the count of entries for a full sync based on the given constraints.
     *
     * @param SyncLogEntryConstraints $constraints
     * @return int
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int;
    
    /**
     * MUST return a collection of models for a full sync based on the given constraints.
     *
     * @param SyncLogEntryConstraints $constraints
     * @return Collection<Model>
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection;
}
