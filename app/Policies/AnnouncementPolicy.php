<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class AnnouncementPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeViewForUserTrait;

    public function markSeen(?User $user): Response
    {
        return $this->isUserResponse($user, 'Only authenticated users can mark announcements as seen.');
    }

    public function markAccepted(?User $user): Response
    {
        return $this->isUserResponse($user, 'Only authenticated users can accept announcements.');
    }
}
