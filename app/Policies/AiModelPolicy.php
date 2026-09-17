<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiModelPolicy
{
    public function viewTools(?\App\Models\User $user): bool
    {
        return $user !== null && app(\App\Services\Admin\PermissionService::class)->isEligible($user);
    }

    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeViewForUserTrait;
}
