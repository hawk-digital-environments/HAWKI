<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractUserEvent
{
    use Dispatchable;

    public function __construct(
        public User $user
    )
    {
    }
}
