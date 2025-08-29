<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Events\InvitationCreateEvent;
use App\Events\InvitationUpdateEvent;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class InvitationHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryTypeEnum
    {
        return SyncLogEntryTypeEnum::ROOM_INVITATION;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $handleSet = function (InvitationCreateEvent|InvitationUpdateEvent $event) {
            $targetUser = $event->invitation->user();

            return $this->createSetPayload(
                $event->invitation,
                $targetUser ?? collect()
            );
        };

        return [
            InvitationCreateEvent::class => $handleSet,
            InvitationUpdateEvent::class => $handleSet
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(Model $model): JsonResource
    {
        /** @var Invitation $model */
        return new InvitationResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Model
    {
        return Invitation::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(Model $model): int
    {
        /** @var Invitation $model */
        return $model->id;
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return $constraints->user->invitations()->count();
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        return $constraints->user->invitations()->orderBy('id')
            ->skip($constraints->offset)
            ->take($constraints->limit)
            ->get();
    }
}
