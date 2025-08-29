<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Models\Room;
use App\Models\User;
use App\Services\SyncLog\Value\SyncLogEntryActionEnum;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class AbstractSyncLogHandler implements SyncLogHandlerInterface
{
    use SyncLogHandlerUtilTrait;
    
    /**
     * Helper to create a {@see SyncLogPayload} for the given model and audience and a SET action.
     * This can be used in your {@see SyncLogHandlerInterface::listeners()} method to create a payload
     * for the given model and audience.
     * @param Model $model The model that is being synced.
     *                     This is usually the model that is being created, updated, or deleted.
     * @param Collection|User $audience The audience that should receive the sync log entry.
     *                                  This can be a collection of users or a single user.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                       If not provided, the log entry will not be associated with a room.
     */
    protected function createSetPayload(
        Model           $model,
        Collection|User $audience,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            model: $model,
            action: SyncLogEntryActionEnum::SET,
            audience: $audience,
            room: $room
        );
    }
    
    /**
     * Helper to create a {@see SyncLogPayload} for the given model and audience and a REMOVE action.
     * This can be used in your {@see SyncLogHandlerInterface::listeners()} method to create a payload
     * for the given model and audience.
     * @param Model $model The model that is being synced.
     *                     This is usually the model that is being created, updated, or deleted.
     * @param Collection|User $audience The audience that should receive the sync log entry.
     *                                  This can be a collection of users or a single user.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                       If not provided, the log entry will not be associated with a room.
     * @return SyncLogPayload
     */
    protected function createRemovePayload(
        Model           $model,
        Collection|User $audience,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            model: $model,
            action: SyncLogEntryActionEnum::REMOVE,
            audience: $audience,
            room: $room
        );
    }
    
    
}
