<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Policies\Traits\AuthorizeViewForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantCategoryPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeViewForUserTrait;
}
