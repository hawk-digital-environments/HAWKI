<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Models\Room;
use App\Models\User;
use App\Services\SyncLog\Value\SyncLogEntryActionEnum;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Similar to {@see AbstractSyncLogHandler}, but for transient data that is NOT stored in the database.
 * This is useful for data that is only relevant for a short period of time and does not need to be persisted.
 * Examples are:
 * - AI typing indicators
 * - Real-time notifications that do not need to be stored
 */
abstract class AbstractTransientSyncLogHandler implements SyncLogHandlerInterface
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
     * @param Collection|User $audience The audience that should receive the sync log entry.
     *                                   This can be a collection of users or a single user.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                        If not provided, the log entry will not be associated with a room.
     * @return SyncLogPayload
     */
    protected function createSetPayload(
        array           $payload,
        User|Collection $audience,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            $this->createDummyModel($payload),
            SyncLogEntryActionEnum::SET,
            $audience,
            $room
        );
    }
    
    /**
     * Helper to create a {@see SyncLogPayload} for the given payload, audience and the REMOVE action.
     * This can be used in your {@see SyncLogHandlerInterface::listeners()} method to create a payload
     * for transient data that should be removed. Because "transient" data is not really stored in the database,
     * the frontend must decide what to do with the REMOVE action.
     * @param User|Collection $audience The audience that should receive the sync log entry.
     *                                   This can be a collection of users or a single user.
     * @param array|null $payload An optional JSON serializable array that represents the data to be synced.
     *                            The data will be sent as-is to the frontend. If not provided, an empty array will be used.
     *                            The frontend must decide what to do with the REMOVE action.
     * @param Room|null $room An optional room that the sync log entry is related to.
     *                       If not provided, the log entry will not be associated with a room.
     * @return SyncLogPayload
     */
    protected function createRemovePayload(
        User|Collection $audience,
        ?array          $payload = null,
        ?Room           $room = null
    ): SyncLogPayload
    {
        return $this->createPayload(
            $this->createDummyModel($payload ?? []),
            SyncLogEntryActionEnum::REMOVE,
            $audience,
            $room
        );
    }
    
    /**
     * @inheritDoc
     */
    final public function convertModelToResource(Model $model): JsonResource
    {
        /**
         * @see AbstractTransientSyncLogHandler::createDummyModel() for the model structure
         */
        return new class($model) extends JsonResource {
            public function toArray($request): array
            {
                return $this->resource->getPayload();
            }
        };
    }
    
    /**
     * @inheritDoc
     */
    final public function findModelById(int $id): ?Model
    {
        return null; // Not available for transient data
    }
    
    /**
     * @inheritDoc
     */
    final public function getIdOfModel(Model $model): int
    {
        return -1; // Not available for transient data
    }
    
    /**
     * @inheritDoc
     */
    final public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return 0; // Not available for transient data
    }
    
    /**
     * @inheritDoc
     */
    final public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        return collect(); // Not available for transient data
    }
    
    /**
     * Builds a dummy model that is used to pass the payload along to the {@see self::convertModelToResource()} method.
     * Yes, this is a bit of a hack, go on, judge me 😎
     * @param array $payload
     * @return Model
     */
    private function createDummyModel(array $payload): Model
    {
        $model = new class() extends Model {
            protected array $payload;
            
            public function getPayload(): array
            {
                return $this->payload;
            }
            
            public function setPayload(array $payload): void
            {
                $this->payload = $payload;
            }
        };
        $model->setPayload($payload);
        return $model;
    }
}
