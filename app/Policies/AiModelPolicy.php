<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiModelPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeViewForUserTrait;
}
