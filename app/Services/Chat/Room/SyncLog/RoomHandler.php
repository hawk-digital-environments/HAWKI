<?php
declare(strict_types=1);


namespace App\Services\Chat\Room\SyncLog;


use App\Events\RoomCreatedEvent;
use App\Events\RoomDeletingEvent;
use App\Events\RoomUpdatedEvent;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\SyncLog\Handlers\AbstractSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractSyncLogHandler<Room>
 */
class RoomHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::ROOM;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        return [
            RoomDeletingEvent::class => function (RoomDeletingEvent $event): SyncLogPayload {
                return $this->createRemovePayload(
                    model: $event->room,
                    audience: $event->room->members->map(fn($member) => $member->user),
                );
            },
            RoomCreatedEvent::class => function (RoomCreatedEvent $event): SyncLogPayload {
                return $this->createSetPayload(
                    model: $event->room,
                    audience: $event->room->members->map(fn($member) => $member->user),
                );
            },
            RoomUpdatedEvent::class => function (RoomUpdatedEvent $event): SyncLogPayload {
                return $this->createSetPayload(
                    model: $event->room,
                    audience: $event->room->members->map(fn($member) => $member->user),
                );
            },
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(mixed $model): JsonResource
    {
        return new RoomResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Room
    {
        return Room::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(mixed $model): int
    {
        return $model->id;
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return $this->getRoomsRelationByConstraints($constraints)->count();
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        return $this->getRoomsRelationByConstraints($constraints)
            ->skip($constraints->offset)
            ->take($constraints->limit)
            ->get();
    }
}
