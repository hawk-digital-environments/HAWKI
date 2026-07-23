<?php
declare(strict_types=1);


namespace App\Policies\Traits;


use App\Models\User;
use Illuminate\Auth\Access\Response;

trait AuthorizeViewForUserTrait
{
    use CommonPolicyChecksTrait;

    public function view(User|null $user): Response
    {
        return $this->isUserResponse($user);
    }
}
