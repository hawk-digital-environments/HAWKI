<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Models\Member;
use App\Models\Room;
use App\Models\User;
use App\Services\SyncLog\Value\SyncLogEntryAction;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * @template T
 */
trait SyncLogHandlerUtilTrait
{
    /**
     * @param SyncLogEntryConstraints $constraints
     * @return BelongsToMany<Room, User>
     */
    protected function getRoomsRelationByConstraints(SyncLogEntryConstraints $constraints): BelongsToMany
    {
        $query = $constraints->user->rooms();
        if ($constraints->roomId !== null) {
            $query = $query->where('rooms.id', $constraints->roomId);
        }
        return $query;
    }
    
    /**
     * @param T $model
     * @param SyncLogEntryAction $action
     * @param Collection|User|null $audience
     * @param Room|null $room
     * @return SyncLogPayload<T>
     */
    private function createPayload(
        mixed                $model,
        SyncLogEntryAction   $action,
        Collection|User|null $audience,
        ?Room                $room = null,
    
    ): SyncLogPayload
    {
        if (!$room && $model instanceof Room) {
            $room = $model;
        }
        
        if ($audience instanceof User) {
            $audience = collect([$audience]);
        } else if ($audience === null && $room !== null) {
            $audience = $room->members->map(fn(Member $member) => $member->user)->filter();
        }
        
        return new SyncLogPayload(
            model: $model,
            action: $action,
            audience: $audience,
            room: $room
        );
    }
}
