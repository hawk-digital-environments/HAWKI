<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

use App\Models\Ai\AiModel;
use App\Models\Room;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for events that track when an AI model is actively generating a response in a room.
 *
 * Provides access to both the {@see Room} in which the generation is taking place and the
 * {@see AiModel} doing the writing. Concrete subclasses are dispatched by the stream controller
 * as a request enters and exits the AI generation phase.
 *
 * @see RoomAiWritingStartedEvent fired when generation begins
 * @see RoomAiWritingEndedEvent   fired when generation ends (success or failure)
 */
abstract readonly class AbstractRoomAiWritingEvent
{
    use Dispatchable;

    public function __construct(
        /** The room in which the AI is (or was) generating a response. */
        public Room    $room,
        /** The AI model that is (or was) generating the response. */
        public AiModel $model
    ) {}
}
