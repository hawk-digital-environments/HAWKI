<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Models\Room;
use App\Models\User;
use App\Services\SyncLog\Handlers\Contract\SyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\UpdatingSyncLogHandlerInterface;
use App\Services\SyncLog\Transient\TransientDataResource;
use App\Services\SyncLog\Value\SyncLogEntryAction;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Similar to {@see AbstractSyncLogHandler}, but for transient data that is NOT stored in the database.
 * This is useful for data that is only relevant for a short period of time and does not need to be persisted.
 *
 * Examples are:
 * - AI typing indicators
 * - Real-time notifications that do not need to be stored
 *
 * @extends SyncLogHandlerInterface<array>
 * @extends UpdatingSyncLogHandlerInterface<array>
 */
abstract class AbstractTransientSyncLogHandler implements SyncLogHandlerInterface, UpdatingSyncLogHandlerInterface
{
    use SyncLogHandlerUtilTrait;
    
    /**
     * Helper to create a {@see SyncLogPayload} for the given payload, audience and the SET action.
     * This can be used in your {@see SyncLogHandlerInterface::listeners()} method to create a payload
     * for transient data.
     *
     * Transient data is NOT store in the database and will only be synced using the websocket connection.
     * This is useful for data that is only relevant for a short period of time and does not need to be persisted.
     *
     * @param array $payload A JSON serializable array that represents the data to be synced. The data will be sent as-is to the frontend.
     * @param Collection|User|null $audience The audience that should receive the sync log entry.
     *                                   This can be a collection of users or a single user.
     *                                   If null, the log entry will be dispatched to all users.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                        If not provided, the log entry will not be associated with a room.
     * @return SyncLogPayload<array>
     */
    protected function createSetPayload(
        array           $payload,
        User|Collection|null $audience,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            $payload,
            SyncLogEntryAction::SET,
            $audience,
            $room
        );
    }
    
    /**
     * Helper to create a {@see SyncLogPayload} for the given payload, audience and the REMOVE action.
     * This can be used in your {@see SyncLogHandlerInterface::listeners()} method to create a payload
     * for transient data that should be removed. Because "transient" data is not really stored in the database,
     * the frontend must decide what to do with the REMOVE action.
     * @param User|Collection|null $audience The audience that should receive the sync log entry.
     *                                   This can be a collection of users or a single user.
     *                                   If null, the log entry will be dispatched to all users.
     * @param array|null $payload An optional JSON serializable array that represents the data to be synced.
     *                            The data will be sent as-is to the frontend. If not provided, an empty array will be used.
     *                            The frontend must decide what to do with the REMOVE action.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                       If not provided, the log entry will not be associated with a room.
     * @return SyncLogPayload<array>
     */
    protected function createRemovePayload(
        User|Collection|null $audience,
        ?array          $payload = null,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            $payload ?? [],
            SyncLogEntryAction::REMOVE,
            $audience,
            $room
        );
    }
    
    /**
     * @inheritDoc
     */
    final public function convertModelToResource(mixed $model): JsonResource
    {
        return new TransientDataResource($model);
    }
    
    /**
     * @inheritDoc
     */
    public function getIdOfModel(mixed $model): int
    {
        // Transient data does not have a persistent ID, so we return 0.
        return 0;
    }
}
