<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantTagPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeViewForUserTrait;

    public function create(User $user): bool
    {
        return true;
    }
}
