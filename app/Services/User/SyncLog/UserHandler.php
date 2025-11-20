<?php
declare(strict_types=1);


namespace App\Services\User\SyncLog;


use App\Events\MemberAddedToRoomEvent;
use App\Events\UserUpdatedEvent;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SyncLog\Handlers\AbstractSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractSyncLogHandler<User>
 */
class UserHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::USER;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        return [
            UserUpdatedEvent::class => function (UserUpdatedEvent $event) {
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
                    $audience
                );
            },
            MemberAddedToRoomEvent::class => function (MemberAddedToRoomEvent $event) {
                // When a member is added to a room, we need to push the new user's data to all existing members of the room.
                return $this->createSetPayload(
                    $event->member->user,
                    $event->member->room->members->map(fn($member) => $member->user),
                    $event->member->room
                );
            }
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(mixed $model): JsonResource
    {
        return new UserResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?User
    {
        return User::find($id);
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
        return max(
            1, // to always include the current user
            $this->getRoomsRelationByConstraints($constraints)
                ->join('members as m', 'rooms.id', '=', 'm.room_id')
                ->distinct()
                ->count()
        );
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        $users = collect();
        $currentOffset = $constraints->offset;
        $remaining = $constraints->limit;
        $containsCurrentUser = false;

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
            ->chunk(200, function ($chunk) use (&$users, &$currentOffset, &$remaining, &$containsCurrentUser, $constraints) {
                foreach ($chunk as $user) {
                    if ($remaining <= 0) {
                        break;
                    }
                    if ($currentOffset > 0) {
                        $currentOffset--;
                        continue;
                    }
                    $users->push($user);
                    if ($user->id === $constraints->user->id) {
                        $containsCurrentUser = true;
                    }
                    $remaining--;
                }
            });
        
        // The currently authenticated user must always be included in the full sync.
        if (!$containsCurrentUser) {
            $users->push($constraints->user);
        }

        return $users;
    }
}
