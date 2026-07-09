<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiToolCapabilityPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
}
