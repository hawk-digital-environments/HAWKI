<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers\Contract;

/**
 * A handler which persists the log into the HAWKI database.
 * This allows for incremental updates of the clients without having to re-sync everything.
 * Being incremental ALSO means that all changes are stored in the database, so that
 * we can reconstruct the state of the client at any point in time.
 * @template T
 * @extends SyncLogHandlerInterface<T>
 */
interface IncrementalSyncLogHandlerInterface extends SyncLogHandlerInterface
{
    /**
     * MUST return the model instance by its ID.
     * @param int $id
     * @return T|null
     */
    public function findModelById(int $id): mixed;
}
