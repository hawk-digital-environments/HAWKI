<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Events\RoomCreateEvent;
use App\Events\RoomRemoveEvent;
use App\Events\RoomUpdateEvent;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class RoomHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryTypeEnum
    {
        return SyncLogEntryTypeEnum::ROOM;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        return [
            RoomRemoveEvent::class => function (RoomRemoveEvent $event): SyncLogPayload {
                return $this->createRemovePayload(
                    model: $event->room,
                    audience: $event->room->members->map(fn($member) => $member->user),
                );
            },
            RoomCreateEvent::class => function (RoomCreateEvent $event): SyncLogPayload {
                return $this->createSetPayload(
                    model: $event->room,
                    audience: $event->room->members->map(fn($member) => $member->user),
                );
            },
            RoomUpdateEvent::class => function (RoomUpdateEvent $event): SyncLogPayload {
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
    public function convertModelToResource(Model $model): JsonResource
    {
        return new RoomResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Model
    {
        return Room::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(Model $model): int
    {
        /** @var Room $model */
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
