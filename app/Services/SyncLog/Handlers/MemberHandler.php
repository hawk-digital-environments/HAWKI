<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Events\MemberAddToRoomEvent;
use App\Events\MemberRemoveFromRoomEvent;
use App\Events\MemberUpdateEvent;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\Room;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class MemberHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryTypeEnum
    {
        return SyncLogEntryTypeEnum::MEMBER;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $getAudience = static function (Member $member) {
            return $member->room->members->map(fn(Member $member) => $member->user)->push($member->user);
        };

        $handleSetEvent = function (MemberUpdateEvent|MemberAddToRoomEvent $event) use ($getAudience) {
            return $this->createSetPayload(
                $event->member,
                $getAudience($event->member),
                $event->member->room
            );
        };

        return [
            MemberUpdateEvent::class => $handleSetEvent,
            MemberAddToRoomEvent::class => $handleSetEvent,
            MemberRemoveFromRoomEvent::class => function (MemberRemoveFromRoomEvent $event) use ($getAudience) {
                return $this->createRemovePayload(
                    $event->member,
                    $getAudience($event->member),
                    $event->member->room
                );
            },
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(Model $model): JsonResource
    {
        /** @var Member $model */
        return new MemberResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Model
    {
        return Member::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(Model $model): int
    {
        /** @var Member $model */
        return $model->id;
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return $this->getRoomsRelationByConstraints($constraints)
            ->withCount('members')
            ->get()
            ->sum('members_count');
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        $members = collect();
        $currentOffset = $constraints->offset;
        $remaining = $constraints->limit;

        $this->getRoomsRelationByConstraints($constraints)
            ->orderBy('rooms.id')
            ->chunk(100, function ($rooms) use (&$members, &$currentOffset, &$remaining) {
                if ($remaining <= 0) {
                    return false;
                }

                foreach ($rooms as $room) {
                    /** @var Room $room */
                    $roomMemberCount = $room->members->count();

                    if ($currentOffset >= $roomMemberCount) {
                        $currentOffset -= $roomMemberCount;
                        continue;
                    }

                    $takeCount = min($remaining, $roomMemberCount - $currentOffset);
                    $roomMembers = $room->members()
                        ->orderBy('members.id')
                        ->skip($currentOffset)
                        ->take($takeCount)
                        ->get();

                    $members = $members->merge($roomMembers);
                    $remaining -= $roomMembers->count();
                    $currentOffset = 0;

                    if ($remaining <= 0) {
                        return false;
                    }
                }

                return true;
            });

        return $members;
    }
}
