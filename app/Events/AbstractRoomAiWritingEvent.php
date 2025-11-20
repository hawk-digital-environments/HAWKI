<?php
declare(strict_types=1);


namespace App\Events;


use App\Models\Room;
use App\Services\AI\Value\AiModel;
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
