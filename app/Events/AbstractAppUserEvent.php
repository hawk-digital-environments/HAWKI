<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\ExtAppUser;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractAppUserEvent
{
    use Dispatchable;
    
    public function __construct(
        public ExtAppUser $user
    )
    {
    }
}
