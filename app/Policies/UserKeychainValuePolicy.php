<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserKeychainValuePolicy
{
    use HandlesAuthorization;
    use AuthorizeViewForUserTrait;
    use AuthorizeViewAnyForUserTrait;

    public function updateBatch(User|null $user): Response
    {
        return $this->isUserResponse($user, 'You need to be an authenticated user to update keychain values.');
    }
}
