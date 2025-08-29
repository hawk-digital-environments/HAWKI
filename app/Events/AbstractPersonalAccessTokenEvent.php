<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

abstract readonly class AbstractPersonalAccessTokenEvent
{
    use Dispatchable;

    public function __construct(
        public User                               $user,
        public PersonalAccessToken|NewAccessToken $token
    )
    {
    }
}
