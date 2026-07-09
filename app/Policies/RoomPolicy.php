<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function view(User $user, Room $room): bool
    {
        return $room->members()->where('user_id', $user->id)->exists();
    }
}
