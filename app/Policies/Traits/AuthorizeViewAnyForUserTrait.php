<?php
declare(strict_types=1);


namespace App\Policies\Traits;


use App\Models\User;
use Illuminate\Auth\Access\Response;

trait AuthorizeViewAnyForUserTrait
{
    use CommonPolicyChecksTrait;

    public function viewAny(User|null $user): Response
    {
        return $this->isUserResponse($user);
    }
}
