<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\Room;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractRoomEvent
{
    use Dispatchable;

    public function __construct(
        public Room $room
    )
    {
    }
}
