<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiModelFlagPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
}
