<?php
declare(strict_types=1);


namespace App\Policies;


use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiToolPolicy
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
}
