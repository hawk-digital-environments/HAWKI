<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use App\Services\Announcements\Values\AnnouncementForUser;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class AnnouncementPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeViewForUserTrait;

    public function update(?User $user, AnnouncementForUser $announcement): Response
    {
        return $this->isUserResponse($user, 'Only authenticated users can update their announcement state.');
    }
}
