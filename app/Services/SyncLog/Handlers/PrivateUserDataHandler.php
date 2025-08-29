<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Events\PrivateUserDataCreateEvent;
use App\Events\PrivateUserdataUpdateEvent;
use App\Http\Resources\PrivateUserDataResource;
use App\Models\PrivateUserData;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PrivateUserDataHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryTypeEnum
    {
        return SyncLogEntryTypeEnum::PRIVATE_USER_DATA;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $handleSet = function (PrivateUserDataCreateEvent|PrivateUserdataUpdateEvent $event) {
            return $this->createSetPayload(
                $event->data,
                $event->data->user
            );
        };

        return [
            PrivateUserDataCreateEvent::class => $handleSet,
            PrivateUserdataUpdateEvent::class => $handleSet
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(Model $model): JsonResource
    {
        /** @var PrivateUserData $model */
        return new PrivateUserDataResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Model
    {
        return PrivateUserData::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(Model $model): int
    {
        /** @var PrivateUserData $model */
        return $model->id;
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        // The keychain is automatically send when the user connects, so we do not need to count it for full sync.
        // This handler is only used for incremental syncs.
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        // The keychain is automatically send when the user connects, so we do not need to count it for full sync.
        // This handler is only used for incremental syncs.
        return collect();
    }
}
