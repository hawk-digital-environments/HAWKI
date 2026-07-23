<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomMemberPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;

    public function view(User $user, Member $member): bool
    {
        return $user->id === $member->user_id || $member->room->members->has('user_id', $user->id);
    }
}
