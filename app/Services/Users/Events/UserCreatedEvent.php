<?php
declare(strict_types=1);


namespace App\Services\Users\Events;


use App\Models\User;

readonly class UserCreatedEvent
{
    public function __construct(
        public User $user
    )
    {
    }
}
