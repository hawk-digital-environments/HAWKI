<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class McpServerPolicy
{
    public function viewTools(?\App\Models\User $user): bool
    {
        return $user !== null && app(\App\Services\Admin\PermissionService::class)->isEligible($user);
    }

    use HandlesAuthorization;
    use AuthorizeViewForUserTrait;
    use AuthorizeViewAnyForUserTrait;
}
