<?php
declare(strict_types=1);


namespace App\Services\Chat\Room\SyncLog;


use App\Events\InvitationCreatedEvent;
use App\Events\InvitationUpdatedEvent;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Services\SyncLog\Handlers\AbstractSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractSyncLogHandler<Invitation>
 */
class InvitationHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::ROOM_INVITATION;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $handleSet = function (InvitationCreatedEvent|InvitationUpdatedEvent $event) {
            $targetUser = $event->invitation->user();

            return $this->createSetPayload(
                $event->invitation,
                $targetUser ?? collect()
            );
        };

        return [
            InvitationCreatedEvent::class => $handleSet,
            InvitationUpdatedEvent::class => $handleSet
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(mixed $model): JsonResource
    {
        return new InvitationResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Invitation
    {
        return Invitation::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(mixed $model): int
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
