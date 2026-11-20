<?php
declare(strict_types=1);


namespace App\Policies\Traits;


use App\Models\User;
use Illuminate\Auth\Access\Response;

trait AuthorizeCreateForUserTrait
{
    use CommonPolicyChecksTrait;

    public function create(User|null $user): Response
    {
        return $this->isUserResponse($user);
    }
}
