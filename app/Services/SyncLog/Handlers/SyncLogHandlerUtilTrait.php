<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Models\Room;
use App\Models\User;
use App\Services\SyncLog\Value\SyncLogEntryActionEnum;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

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
    
    private function createPayload(
        Model                  $model,
        SyncLogEntryActionEnum $action,
        Collection|User        $audience,
        ?Room                  $room = null,
    
    ): SyncLogPayload
    {
        if (!$room && $model instanceof Room) {
            $room = $model;
        }
        
        return new SyncLogPayload(
            model: $model,
            action: $action,
            audience: $audience instanceof User
                ? collect([$audience])
                : $audience,
            room: $room
        );
    }
}
