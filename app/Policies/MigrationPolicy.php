<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class MigrationPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeViewForUserTrait;

    public function applyMigration(?User $user): Response
    {
        return $this->isUserResponse($user, 'Only authenticated users can apply migrations.');
    }
}
