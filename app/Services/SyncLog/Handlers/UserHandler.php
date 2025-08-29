<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Events\UserResetEvent;
use App\Events\UserUpdateEvent;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class UserHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryTypeEnum
    {
        return SyncLogEntryTypeEnum::USER;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        return [
            UserUpdateEvent::class => function (UserUpdateEvent $event) {
                $user = $event->user;

                // The audience for a user update event includes the user themselves and all members of their rooms.
                $audience = collect([$user]);
                foreach ($user->rooms as $room) {
                    foreach ($room->members as $member) {
                        $audience->push($member->user);
                    }
                }

                return $this->createSetPayload(
                    $event->user,
                    $audience->unique(fn($user) => $user->id)
                );
            },
            UserResetEvent::class => function (UserResetEvent $event) {
                return $this->createRemovePayload(
                    $event->user,
                    $event->user
                );
            },
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(Model $model): JsonResource
    {
        return new UserResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Model
    {
        return User::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(Model $model): int
    {
        /** @var User $model */
        return $model->id;
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return $this->getRoomsRelationByConstraints($constraints)
            ->join('members as m', 'rooms.id', '=', 'm.room_id')
            ->distinct()
            ->count();
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        $users = collect();
        $currentOffset = $constraints->offset;
        $remaining = $constraints->limit;

        User::query()
            ->whereIn('id',
                $this->getRoomsRelationByConstraints($constraints)
                    ->join('members as m', 'rooms.id', '=', 'm.room_id')
                    ->distinct()
                    ->offset($currentOffset)
                    ->limit($remaining)
                    ->pluck('m.user_id')
                    ->toArray()
            )
            ->chunk(200, function ($chunk) use (&$users, &$currentOffset, &$remaining) {
                foreach ($chunk as $user) {
                    if ($remaining <= 0) {
                        break;
                    }
                    if ($currentOffset > 0) {
                        $currentOffset--;
                        continue;
                    }
                    $users->push($user);
                    $remaining--;
                }
            });

        return $users;
    }
}
