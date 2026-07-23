<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiModelDescriptionPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
}
