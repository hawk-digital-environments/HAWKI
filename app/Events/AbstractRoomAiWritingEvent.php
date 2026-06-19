<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\Ai\AiModel;
use App\Models\Room;
use Illuminate\Foundation\Events\Dispatchable;

abstract readonly class AbstractRoomAiWritingEvent
{
    use Dispatchable;

    public function __construct(
        public Room    $room,
        public AiModel $model
    )
    {
    }
}
