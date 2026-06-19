<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class McpServerPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewForUserTrait;
    use AuthorizeViewAnyForUserTrait;
}
