<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\PrivateUserData;
use Illuminate\Foundation\Events\Dispatchable;

readonly abstract class AbstractPrivateUserDataEvent
{
    use Dispatchable;

    public function __construct(
        public PrivateUserData $data
    )
    {
    }
}
