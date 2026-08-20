<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewForUserTrait;
    use AuthorizeViewAnyForUserTrait;

    /**
     * Users may only update their own profile.
     */
    public function update(User|null $user, User $model): bool
    {
        return $this->isSameUser($user, $model);
    }
}
