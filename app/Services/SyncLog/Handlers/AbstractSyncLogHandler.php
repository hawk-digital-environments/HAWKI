<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Models\Room;
use App\Models\User;
use App\Services\SyncLog\Handlers\Contract\FullSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\IncrementalSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\SyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\UpdatingSyncLogHandlerInterface;
use App\Services\SyncLog\Value\SyncLogEntryAction;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Support\Collection;

/**
 * Your base class for creating the most common types of sync log handlers.
 *
 * It combines automatic full sync support, persisting support and update support.
 * For transient data that is NOT stored in the database, use {@see AbstractTransientSyncLogHandler}.
 * For read-only data that is only synced on full sync, use {@see AbstractStaticSyncLogHandler}.
 *
 * @template T
 * @implements SyncLogHandlerInterface<T>
 * @implements FullSyncLogHandlerInterface<T>
 * @implements IncrementalSyncLogHandlerInterface<T>
 * @implements UpdatingSyncLogHandlerInterface<T>
 */
abstract class AbstractSyncLogHandler implements SyncLogHandlerInterface, FullSyncLogHandlerInterface, IncrementalSyncLogHandlerInterface, UpdatingSyncLogHandlerInterface
{
    use SyncLogHandlerUtilTrait;
    
    /**
     * Helper to create a {@see SyncLogPayload} for the given model and audience and a SET action.
     * This can be used in your {@see SyncLogHandlerInterface::listeners()} method to create a payload
     * for the given model and audience.
     * @param T $model The model that is being synced.
     *                     This is usually the model that is being created, updated, or deleted.
     * @param Collection|User|null $audience The audience that should receive the sync log entry.
     *                                  This can be a collection of users or a single user.
     *                                  If null, the log entry will be dispatched to all users.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                       If not provided, the log entry will not be associated with a room.
     */
    protected function createSetPayload(
        mixed                $model,
        Collection|User|null $audience,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            model: $model,
            action: SyncLogEntryAction::SET,
            audience: $audience,
            room: $room
        );
    }
    
    /**
     * Helper to create a {@see SyncLogPayload} for the given model and audience and a REMOVE action.
     * This can be used in your {@see SyncLogHandlerInterface::listeners()} method to create a payload
     * for the given model and audience.
     * @param T $model The model that is being synced.
     *                     This is usually the model that is being created, updated, or deleted.
     * @param Collection|User|null $audience The audience that should receive the sync log entry.
     *                                  This can be a collection of users or a single user.
     *                                  If null, the log entry will be dispatched to all users.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                       If not provided, the log entry will not be associated with a room.
     * @return SyncLogPayload
     */
    protected function createRemovePayload(
        mixed                $model,
        Collection|User|null $audience,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            model: $model,
            action: SyncLogEntryAction::REMOVE,
            audience: $audience,
            room: $room
        );
    }
}
